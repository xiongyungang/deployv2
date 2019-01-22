<?php

namespace App\Jobs;

use App\Modelconfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maclof\Kubernetes\Models\DeleteOptions;
use Maclof\Kubernetes\Models\Job;
use Maclof\Kubernetes\Models\PersistentVolumeClaim;
use phpseclib\Crypt\RSA;
class DeployModelconfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
     * @var Modelconfig
     *
     */
    private $modelconfig;

    /**
     * @var \Maclof\Kubernetes\Client
     */
    private $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Modelconfig $modelconfig)
    {
        //
        $this->modelconfig = $modelconfig;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::warning("Modelconfig check or create ".$this->modelconfig->name);

        $modelconfig = Modelconfig::find($this->modelconfig->id);

        if (!$modelconfig) {
            \Log::warning("modelconfig " . $this->modelconfig->name . " has been destroyed");
            return;
        }

        //失败退出队列
        if($this->modelconfig->state==config('state.failed')){
            \Log::warning("Modelconfig ".$this->modelconfig->name . "is failed");
            return;
        }

        //异常大于三次，内部错误，退出队列
        if ($this->attempts() > 3) {
            \Log::warning("deployModelconfigJob : ".$this->modelconfig->name."Failure is greater than 3 times begins to delete.");
            $this->delete();
        }

        $this->client = $this->modelconfig->app->cluster->client();

        $state = $this->modelconfig->state;
        $desired_state = $this->modelconfig->desired_state;

        if ($state != $modelconfig->state || $desired_state != $modelconfig->desired_state) {
            \Log::warning("modelconfig " . $modelconfig->name . "'s state or desired_state has been changed");
            return;
        }

        if ($state == $desired_state && ($state == config('state.started') || $state == config('state.restarted'))) {
            if ($this->allAvailable()) {
                return;
            }
        }

        switch ($desired_state) {
            case config('state.restarted'):
            case config('state.started'):
                $this->processStarted();
                break;
            case config('state.destroyed'):
                $this->processDestroyed();
                break;
        }
    }

    private function allAvailable()
    {
        if ($this->pvcAvailable() && $this->jobCompleted()) {
            return true;
        }
        return false;
    }

    private function pvcAvailable()
    {
        $pvc = $this->getPvc();
        if(!$pvc){
            return false;
        }
        \Log::info('pvc ' . $this->modelconfig->name . ' status: ' . json_encode($pvc->toArray()['status']));
        return $pvc->toArray()['status']['phase'] == 'Bound';
    }

    private function jobCompleted()
    {
        $job = $this->getJob();
        try{
            if ($job) {
                $commit = $job->toArray()['metadata']['annotations']['commit'];
                $command = $job->toArray()['metadata']['annotations']['command'];
                $envs = $job->toArray()['metadata']['annotations']['envs'];
                //commit不同，更新
                if ($commit != $this->modelconfig->commit || $command != $this->modelconfig->command
                    || $envs != $this->modelconfig->envs) {
                    return false;
                }

                $status = $job->toArray()['status'];
                \Log::info("job status :".json_encode($status));

                if (isset($status['succeeded']) && $status['succeeded'] == 1) {
                    return true;
                }

                //todo:?????
                if (strtotime($status['startTime']) < time() - 600) {
                    \Log::warning('job ' . $this->modelconfig->name . ' over 10m, auto delete,start create');
                    //todo:faild send message

                    //$this->tryDeleteJob(); //10分钟的任务自动删除
                }

                if (isset($status['failed'])) {
                    \Log::warning('job ' . $this->modelconfig->name . ' failed');

                    //todo:faild send message
                    $this->modelconfig->update(['state' =>config('state.failed')]);
                    requestAsync_post($this->modelconfig->callback_url,"modelconfig",["status"=>$status],$this->modelconfig->attributesToArray());
                    return false;
                }
            }
        }catch (\Exception $exception){
        }
        return false;
    }

    /**
     * @return \Maclof\Kubernetes\Models\Job|null
     */
    private function getJob()
    {
        try {
            if (!$this->client->jobs()->exists($this->modelconfig->name)) {
                return null;
            }
            return $this->client->jobs()
                ->setLabelSelector(['app' => $this->modelconfig->name])
                ->first();
        } catch (\Exception $exception) {
            return null;
        }
    }
    /**
     * @return \Maclof\Kubernetes\Models\PersistentVolumeClaim|null
     */
    private function getPvc()
    {
        try {
            if (!$this->client->persistentVolumeClaims()->exists($this->modelconfig->name)) {
                \Log::info('pvc ' . $this->modelconfig->name . ' not exists');
                return null;
            }
            return $pvc = $this->client->persistentVolumeClaims()
                ->setLabelSelector(['app' => $this->modelconfig->name])
                ->first();
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function tryDeleteJob()
    {
        try {
            if ($this->client->jobs()->exists($this->modelconfig->name)) {
                $this->client->jobs()->deleteByName(
                    $this->modelconfig->name,
                    new DeleteOptions(['propagationPolicy' => 'Background'])
                );
            }
        } catch (\Exception $exception) {
            \Log::warning($exception->getMessage());
        }
    }

    private function tryDeletePvc()
    {
        try {
            if ($this->client->persistentVolumeClaims()->exists($this->modelconfig->name)) {

                    $this->client->persistentVolumeClaims()->deleteByName(
                        $this->modelconfig->name,
                        new DeleteOptions(['propagationPolicy' => 'Background'])
                    );

            }
        } catch (\Exception $exception) {
            \Log::warning($exception->getMessage());
        }
    }

    private function tryCreatePvc()
    {
        $pvc = new PersistentVolumeClaim([
            'metadata' => [
                'name' => $this->modelconfig->name,
                'labels' => $this->commonLabels(),
                'annotations' => [
                    'commit' => $this->modelconfig->commit,
                ],
            ],
            'spec' => [
                'accessModes' => ['ReadWriteMany'],
                'storageClassName' => 'nfs-ssd',
                'resources' => ['requests' => ['storage' => '1Gi'],],
                'selector' => ['matchLabels' => $this->commonLabels(),],
            ],
        ]);

        try {
            if (!$this->client->persistentVolumeClaims()->exists($this->modelconfig->name)) {
                $this->client->persistentVolumeClaims()->create($pvc);
            }
        } catch (\Exception $exception) {
        }
    }

    private function commonLabels()
    {
        $labels = [
            'app' => $this->modelconfig->name,
            'appkey' => $this->modelconfig->app->appkey,
            'channel' => '' . $this->modelconfig->app->channel,
            'commit' => $this->modelconfig->commit,
        ];
        if($this->modelconfig->labels){
            $customLabels = json_decode($this->modelconfig->labels, true);
            $label=[];
            foreach ($customLabels as $k => $v) {
                if (is_int($v)) {
                    $v = (string)$v;
                }
                $label[$k] = $v;
            }
            $labels = array_merge($labels,$label);
        }
        return $labels;
    }

    private function commonEnvs()
    {
        $rsa = new RSA();
        $rsa->loadKey(base64_decode($this->modelconfig->app->ssh_private_key));

        $envs = [
            //todo:待优化
            ['name' => 'PROJECT_GIT_URL', 'value' => $this->modelconfig->repo->git_ssh_url],
            ['name' => 'PROJECT_COMMIT', 'value' => $this->modelconfig->commit],
            //todo: PROJECT_BRANCH and PROJECT_COMMIT git脚本中判断的是Branch
            ['name' => 'PROJECT_BRANCH', 'value' => "remotes/origin/".$this->modelconfig->commit],
            ['name' => 'PROJECT_GIT_COMMIT', 'value' => "remotes/origin/".$this->modelconfig->commit],
            ['name' => 'GIT_PRIVATE_KEY', 'value' => $this->modelconfig->app->git_private_key],
            ['name' => 'COMMAND_MODEL', 'value' => $this->modelconfig->command],
        ];

        $customEnvs = json_decode($this->modelconfig->envs, true);

        foreach ($customEnvs as $k => $v) {
            if (is_int($v)) {
                $v = (string)$v;
            }
            $envs[] = ['name' => $k, 'value' => $v];
        }

        return $envs;
    }

    private function tryCreateJob()
    {
        if ($this->jobCompleted()) {
            return;
        }

        // job执行完成后，还会留在kubernetes中，需要先删除，才能创建同名job
        $this->tryDeleteJob();

        $image = 'registry.cn-shanghai.aliyuncs.com/itfarm/modeconfig:' . $this->modelconfig->repo->type;

        $yaml = [
            'metadata' => [
                'name' => $this->modelconfig->name,
                'labels' => $this->commonLabels(),
                'annotations' => [
                    'commit' => $this->modelconfig->commit,
                    'command'=>$this->modelconfig->command,
                    'envs' =>$this->modelconfig->envs,
                ],
            ],
            'spec' => [
                'template' => [
                    'spec' => [
                        'imagePullSecrets' => [['name' => 'aliyun-registry-vpc']],
                        'containers' => [
                            [
                                'name' => 'job',
                                'image' => $image,
                                'env' => $this->commonEnvs(),
                                'command' => ["/bin/bash","/etc/my_init.d/init_model.sh"],
                                'volumeMounts' => [
                                    [
                                        'mountPath' => '/tmp/.composer/cache',
                                        'name' => 'composer-cache',
                                    ],
                                ],
                            ],
                        ],
                        'restartPolicy' => 'Never',
                        'volumes' => [
                            [
                                'name' => 'composer-cache',
                                'persistentVolumeClaim' => [
                                    'claimName' => 'composer-cache',
                                ],
                            ],
                        ],
                    ],
                ],
                'backoffLimit' => 1,
            ],
        ];

        $yaml['spec']['template']['spec']['containers'][0]['volumeMounts'][] = [
            'mountPath' => '/opt/ci123/www/html',
            'subPath' => "code",
            'name' => 'code-data',
        ];
        $yaml['spec']['template']['spec']['volumes'][] = [
            'name' => 'code-data',
            'persistentVolumeClaim' => [
                'claimName' => $this->modelconfig->name,
            ],
        ];


        $job = new Job($yaml);

        $this->client->jobs()->create($job);
    }

    private function processStarted()
    {

        if ($this->allAvailable()) {
            \Log::warning("Completion ".$this->modelconfig->name.
                ":".$this->modelconfig->state."->".$this->modelconfig->desired_state);

            $this->modelconfig->update(['state' => $this->modelconfig->desired_state]);

            //成功通知
            requestAsync_post($this->modelconfig->callback_url,"modelconfig",["logs"=>"modelconfig success"],
                $this->modelconfig->attributesToArray());
            return;
        }


        // 创建pvc
        $this->tryCreatePvc();
        if (!$this->pvcAvailable()) {
            \Log::info('pvc ' . $this->modelconfig->name . ' not available');
            return;
        }

        // 创建job
        $this->tryCreateJob();
        if (!$this->jobCompleted()) {
            \Log::info('job not completed');
            return;
        }
        \Log::warning("modelcongfig ".$this->modelconfig->name." created");
    }

    private function processDestroyed(){

        $job = $this->getJob();
        $pvc = $this->getPvc();
        if(!$job && !$pvc){
            $this->modelconfig->update(['state' => $this->modelconfig->desired_state]);
            try{
                $this->modelconfig->delete();
            }catch(\Exception $exception){

            }
            return;
        }
        $this->tryDeleteJob();
        $this->tryDeletePvc();
    }
}
