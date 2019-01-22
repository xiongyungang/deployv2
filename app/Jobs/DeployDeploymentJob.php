<?php
/**
 * 1. 部署的目标状态（desired_state）设计为三个
 *  - started 已运行
 *  - restarted 已重新运行
 *  - destroyed 已销毁
 * 2. 当code_in_image为false时，将创建pv，并在该pv中创建两个目录: odd even
 *  第一次部署使用odd，后续的更新交替使用
 * 3. 部署需要创建name相同的pvc service ingress job deployment（kubernetes中的概念）
 *  首先创建pvc和job，job处理代码拉取、composer install、db migrate等，根据repo的type、及code_in_image等值做不同的处理
 *  当job执行成功后，再创建deployment service ingress
 *  当type为php-*，且code_in_image为false时，执行代码拉取、composer install（根目录含有composer.json时）、db migrate（根目录含有phinx.php时）
 */

namespace App\Jobs;

use App\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maclof\Kubernetes\Models\DeleteOptions;
use Maclof\Kubernetes\Models\Ingress;
use Maclof\Kubernetes\Models\Job;
use Maclof\Kubernetes\Models\PersistentVolumeClaim;
use Maclof\Kubernetes\Models\Service;
use phpseclib\Crypt\RSA;

class DeployDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Deployment
     */
    protected $deployment;

    /**
     * @var \Maclof\Kubernetes\Client
     */
    private $client;

    /**
     *
     * @var string
     */
    private $webpath;

    /**
     *
     * @var boolean
     */
    private $isUpdata = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Deployment $deployment,bool $isUpdata=false)
    {
        $this->deployment = $deployment;
        $this->isUpdata = $isUpdata;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        if($this->deployment->state==config('state.failed')){
            \Log::warning("Deployment ".$this->deployment->name . "is failed");
            return;
        }
        $this->client = $this->deployment->app->cluster->client();

        $deployment = Deployment::find($this->deployment->id);
        if (!$deployment) {
            \Log::warning("Deployment " . $this->deployment->name . " has been destroyed");
            return;
        }

        $state = $this->deployment->state;
        $desired_state = $this->deployment->desired_state;

        // 另外一个job已经将deployment的状态改变了，不做任何处理（应该属于低概率事件，所以报warning）
        if ($state != $deployment->state || $desired_state != $deployment->desired_state) {
            \Log::warning("Deployment " . $deployment->name . "'s state or desired_state has been changed");
            return;
        }

        // 状态为 started 或 restarted，但实际并没有正常运行
        if ($state == $desired_state && ($state == config('state.started') || $state == config('state.restarted'))) {
            if (!$this->allAvailable()) {
                $this->deployment->update(['state' => config('state.pending')]);
            }
            return;
        }

        switch ($desired_state) {
            case config('state.started'):
            case config('state.restarted'):
                $this->processStarted();
                break;
            case config('state.destroyed'):
                $this->processDestroyed();
                break;
        }
    }

    private function allAvailable()
    {
        $deployment = $this->getDeployment();
        if (!$deployment) {
            return false;
        }

        try {
            if($deployment->toArray()['metadata']['annotations']['commit'] !=$this->deployment->commit){
                //需要更新
                return false;
            }
            if($deployment->toArray()['metadata']['annotations']['envs'] !=md5($this->deployment->envs)){
                //需要更新
                return false;
            }
            if($deployment->toArray()['status']['readyReplicas'] == 0){
                $this->deployment->update(['state' => config('state.failed')]);
                requestAsync_post($this->deployment->callback_url,
                    "deployment",["status" => $deployment->toArray()['status']],
                    $this->deployment->attributesToArray());
                return false;
            }
            $service = $this->client->services()
                ->setLabelSelector(['app' => $this->deployment->name])
                ->first();
            if (!$service) {
                return false;
            }

            $ingress = $this->client->ingresses()
                ->setLabelSelector(['app' => $this->deployment->name])
                ->first();
            if (!$ingress) {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }

    private function processStarted()
    {
        if(!$this->isUpdata){
            if ($this->allAvailable()) {
                $this->deployment->update(['state' => $this->deployment->desired_state]);
                requestAsync_post($this->deployment->callback_url,
                    "deployment",["logs"=>"deployment success"],$this->deployment->attributesToArray());
                return;
            }
        }

        // 创建pvc
        $this->tryCreatePvc();
        if (!$this->pvcAvailable()) {
            \Log::info('pvc ' . $this->deployment->name . ' not available');
            return;
        }

        $deployment = $this->getDeployment();
        if (!$deployment) {
            $this->webpath = 'odd';
        } else {
            $commit = $deployment->toArray()['metadata']['annotations']['commit'];
            $webpath = $deployment->toArray()['metadata']['annotations']['webpath'];

            if ($commit != $this->deployment->commit) {
                $this->webpath = $webpath == 'odd' ? 'even' : 'odd';
            } else {
                $this->webpath = $webpath;
            }
        }

        // 创建job
        $this->tryCreateJob();
        if (!$this->jobCompleted()) {
            \Log::info('job not completed');
            return;
        }

        $this->tryCreateDeployment();
        $this->tryCreateService();
        $this->tryCreateIngress();
    }

    /**
     * @throws \Exception
     */
    private function processDestroyed()
    {
        $name = $this->deployment->name;
        $success = true;

        $deleteOption = new DeleteOptions(['propagationPolicy' => 'Foreground']);

        $kubernetesRepositories = [
            $this->client->ingresses(),
            $this->client->services(),
            $this->client->deployments(),
            $this->client->jobs(),
            $this->client->persistentVolumeClaims(),
        ];

        foreach ($kubernetesRepositories as $kubernetesRepository) {
            try {
                if ($kubernetesRepository->exists($name)) {
                    $success = false;
                    $kubernetesRepository->deleteByName($name, $deleteOption);
                }
            } catch (\Exception $exception) {
            }
        }

        if ($success) {
            $this->deployment->delete();
        }
    }

    private function commonLabels()
    {
        $labels = [
        'app' => $this->deployment->name,
        'appkey' => $this->deployment->app->appkey,
        'channel' => '' . $this->deployment->app->channel,
        'userAppkey' => $this->deployment->app->user_appkey,
        ];
        if($this->deployment->labels){
            $customLabels = json_decode($this->deployment->labels, true);
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
        $rsa->loadKey(base64_decode($this->deployment->app->ssh_private_key));

        $envs = [
            [
                'name' => 'SSH_PUBLIC_KEY',
                'value' => base64_encode($rsa->getPublicKey(RSA::PUBLIC_FORMAT_OPENSSH)),
            ],
        ];

        if ($this->deployment->code_in_image == 0) {
            $envs = [
                ['name' => 'PROJECT_GIT_URL', 'value' => $this->deployment->repo->git_ssh_url],
                ['name' => 'PROJECT_COMMIT', 'value' => $this->deployment->commit],
                //todo: PROJECT_BRANCH and PROJECT_COMMIT git脚本中判断的是Branch
                ['name' => 'PROJECT_BRANCH', 'value' => "remotes/origin/" . $this->deployment->commit],
                ['name' => 'PROJECT_GIT_COMMIT', 'value' => "remotes/origin/" . $this->deployment->commit],
                ['name' => 'GIT_PRIVATE_KEY', 'value' => $this->deployment->app->git_private_key],
                ['name' => 'ENVIRONMENT','value' => 'production'],
            ];
        }

        $customEnvs = json_decode($this->deployment->envs, true);

        foreach ($customEnvs as $k => $v) {
            if (is_int($v)) {
                $v = (string)$v;
            }
            $envs[] = ['name' => $k, 'value' => $v];
        }

        return $envs;
    }

    private function tryCreateDeployment()
    {
        \Log::info('try craete deployment ' . $this->deployment->name);

        $image = 'registry-vpc.cn-shanghai.aliyuncs.com/itfarm/lnmp:' . $this->deployment->repo->type;
        if ($this->deployment->image_url) {
            $image = $this->deployment->image_url;
        }

        $yaml = [
            'metadata' => [
                'name' => $this->deployment->name,
                'labels' => $this->commonLabels(),
                'annotations' => [
                    'webpath' => $this->webpath,
                    'commit' => $this->deployment->commit,
                    'time_deployment' => "".$this->deployment->updated_at->toDateTimeString(),
                    'envs' => md5($this->deployment->envs),
                    ],
            ],
            'spec' => [
                'replicas' => 2,
                'selector' => ['matchLabels' => $this->commonLabels()],
                'template' => [
                    'metadata' => ['labels' => $this->commonLabels()],
                    'spec' => [
                        'imagePullSecrets' => [['name' => 'aliyun-registry-vpc']],
                        'containers' => [
                            [
                                'name' => 'deployment',
                                'imagePullPolicy' => 'IfNotPresent',
                                'image' => $image,
                                'ports' => [
                                    [
                                        'name' => 'http',
                                        'containerPort' => 80,
                                        'protocol' => 'TCP',
                                    ],
                                ],
                                'env' => $this->commonEnvs(),
                                'resources' => [
                                    'limits' => ['cpu' => '1', 'memory' => '1024Mi'],
                                    'requests' => ['cpu' => '100m', 'memory' => '128Mi'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($this->deployment->code_in_image == 0) {
            $yaml['spec']['template']['spec']['containers'][0]['volumeMounts'] = [
                [
                    'mountPath' => '/opt/ci123/www/html',
                    'subPath' => $this->webpath,
                    'name' => 'code-data',
                ],
                [
                    'mountPath' => '/var/run/cidata-events/',
                    'name' => 'cidata-cache',
                ],
            ];

            $yaml['spec']['template']['spec']['volumes'] = [
                [
                    'name' => 'code-data',
                    'persistentVolumeClaim' => [
                        'claimName' => $this->deployment->name,
                    ],
                ],
                [
                    'name' => 'cidata-cache',
                    'emptyDir'=>new class{},
                ],
            ];
        } else {
            $yaml['spec']['template']['spec']['containers'][0]['image'] = $this->deployment->image_url;
        }
        $yaml['spec']['template']['spec']['containers'][1]['name']="cidata-reporter";
        $yaml['spec']['template']['spec']['containers'][1]['image']="registry-vpc.cn-shanghai.aliyuncs.com/cidata/php-sdk-exporter:latest";
        $yaml['spec']['template']['spec']['containers'][1]['volumeMounts'] =[
            [
                'mountPath' => '/var/run/cidata-events/',
                'name' => 'cidata-cache',
            ],
        ];

        $deployment = new \Maclof\Kubernetes\Models\Deployment($yaml);
        try {
            if ($this->client->deployments()->exists($this->deployment->name)) {

                \Log::info('patch deployment ' . $this->deployment->name);

                $this->client->deployments()->patch($deployment);
            } else {
                \Log::info('create deployment ' . $this->deployment->name);

                $this->client->deployments()->create($deployment);
            }
        } catch (\Exception $exception) {

            \Log::info('create deployment ' . $this->deployment->name);

            $this->client->deployments()->create($deployment);
        }
    }

    /**
     * @return \Maclof\Kubernetes\Models\Deployment|null
     */
    private function getDeployment()
    {
        try {
            if (!$this->client->deployments()->exists($this->deployment->name)) {
                return null;
            }
            return $this->client->deployments()
                ->setLabelSelector(['app' => $this->deployment->name])
                ->first();
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function tryCreateJob()
    {
        if ($this->jobCompleted()) {
            return;
        }

        $job = $this->getJob();
        if ($job) {
            $commit = $job->toArray()['metadata']['annotations']['commit'];
            $webpath = $job->toArray()['metadata']['annotations']['webpath'];

            if ($commit == $this->deployment->commit && $webpath == $this->webpath) {
                return; //符合条件的job正在运行，尚未结束
            }
        }

        // job执行完成后，还会留在kubernetes中，需要先删除，才能创建同名job
        $this->tryDeleteJob();

        $image = 'registry-vpc.cn-shanghai.aliyuncs.com/itfarm/toolbox:' . $this->deployment->repo->type;
        if ($this->deployment->image_url) {
            $image = $this->deployment->image_url;
        }

        $yaml = [
            'metadata' => [
                'name' => $this->deployment->name,
                'labels' => $this->commonLabels(),
                'annotations' => ['webpath' => $this->webpath, 'commit' => $this->deployment->commit],
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
                                'command' => ["/sbin/my_init", "--", "ls", "-l"],
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

        if ($this->deployment->code_in_image == 0) {
            $yaml['spec']['template']['spec']['containers'][0]['volumeMounts'][] = [
                'mountPath' => '/opt/ci123/www/html',
                'subPath' => $this->webpath,
                'name' => 'code-data',
            ];
            $yaml['spec']['template']['spec']['volumes'][] = [
                'name' => 'code-data',
                'persistentVolumeClaim' => [
                    'claimName' => $this->deployment->name,
                ],
            ];
        }

        $job = new Job($yaml);

        $this->client->jobs()->create($job);
    }

    private function tryDeleteJob()
    {
        try {
            if ($this->client->jobs()->exists($this->deployment->name)) {
                $this->client->jobs()->deleteByName(
                    $this->deployment->name,
                    new DeleteOptions(['propagationPolicy' => 'Background'])
                );
            }
        } catch (\Exception $exception) {
            \Log::warning($exception->getMessage());
        }
    }

    private function jobCompleted()
    {
        $job = $this->getJob();

        if ($job) {
            $commit = $job->toArray()['metadata']['annotations']['commit'];
            $webpath = $job->toArray()['metadata']['annotations']['webpath'];
            if ($commit != $this->deployment->commit || $webpath != $this->webpath) {
                return false;
            }
            $status = $job->toArray()['status'];

            if (isset($status['succeeded']) && $status['succeeded'] == 1) {
                return true;
            }
            //todo:?????
//            if (strtotime($status['startTime']) < time() - 600) {
//                \Log::warning('job ' . $this->deployment->name . ' over 10m, auto delete');
//                $this->tryDeleteJob(); //10分钟的任务自动删除
//            }
            if(isset($status['failed'])){
                $this->deployment->update(['state' => config('state.failed')]);
                requestAsync_post($this->deployment->callback_url,
                    "deployment",["status" => $job->toArray()['status']],
                    $this->deployment->attributesToArray());
            }
        }

        return false;
    }

    /**
     * @return \Maclof\Kubernetes\Models\Job|null
     */
    private function getJob()
    {
        try {
            if (!$this->client->jobs()->exists($this->deployment->name)) {
                return null;
            }
            return $this->client->jobs()
                ->setLabelSelector(['app' => $this->deployment->name])
                ->first();
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function tryCreatePvc()
    {
        $pvc = new PersistentVolumeClaim([
            'metadata' => [
                'name' => $this->deployment->name,
                'labels' => $this->commonLabels(),
            ],
            'spec' => [
                'accessModes' => ['ReadWriteMany'],
                'storageClassName' => 'nfs-ssd',
                'resources' => ['requests' => ['storage' => '1Gi'],],
                'selector' => ['matchLabels' => $this->commonLabels(),],
            ],
        ]);

        try {
            if (!$this->client->persistentVolumeClaims()->exists($this->deployment->name)) {
                $this->client->persistentVolumeClaims()->create($pvc);
            }
        } catch (\Exception $exception) {
        }
    }

    private function pvcAvailable()
    {
        try {
            if (!$this->client->persistentVolumeClaims()->exists($this->deployment->name)) {
                \Log::info('pvc ' . $this->deployment->name . ' not exists');
                return false;
            }
        } catch (\Exception $exception) {
        }

        $pvc = $this->client->persistentVolumeClaims()
            ->setLabelSelector(['app' => $this->deployment->name])
            ->first();

        \Log::info('pvc ' . $this->deployment->name . ' status: ' . json_encode($pvc->toArray()['status']));
        return $pvc->toArray()['status']['phase'] == 'Bound';
    }

    private function tryCreateService()
    {
        \Log::info('try create service ' . $this->deployment->name);

        $service = new Service([
            'metadata' => [
                'name' => $this->deployment->name,
                'labels' => $this->commonLabels(),
            ],
            'spec' => [
                'type' => 'ClusterIP',
                'ports' => [
                    ['port' => 80, 'targetPort' => 'http', 'protocol' => 'TCP', 'name' => 'http'],
                ],
                'selector' => $this->commonLabels(),
            ],
        ]);

        try {
            if ($this->client->services()->exists($this->deployment->name)) {

                \Log::info('patch service ' . $this->deployment->name);

                $this->client->services()->patch($service);
            } else {

                \Log::info('crate service ' . $this->deployment->name);

                $this->client->services()->create($service);
            }
        } catch (\Exception $exception) {

            \Log::info('crate service ' . $this->deployment->name);

            $this->client->services()->create($service);
        }
    }

    private function tryCreateIngress()
    {
        \Log::info('try create ingress ' . $this->deployment->name);

        $ingress = new Ingress([
            'metadata' => [
                'name' => $this->deployment->name,
                'labels' => $this->commonLabels(),
            ],
            'spec' => [
                'tls' => [
                    ['hosts' => [$this->deployment->name . '-dev.oneitfarm.com',], 'secretName' => 'oneitfarm-secret'],
                ],
                'rules' => [
                    [
                        'host' => $this->deployment->name . '-dev.oneitfarm.com',
                        'http' => [
                            'paths' => [
                                [
                                    'path' => '/',
                                    'backend' => ['serviceName' => $this->deployment->name, 'servicePort' => 'http'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            if ($this->client->ingresses()->exists($this->deployment->name)) {

                \Log::info('patch ingress ' . $this->deployment->name);

                $this->client->ingresses()->patch($ingress);
            } else {

                \Log::info('create ingress ' . $this->deployment->name);

                $this->client->ingresses()->create($ingress);
            }
        } catch (\Exception $exception) {

            \Log::info('create ingress ' . $this->deployment->name);

            $this->client->ingresses()->create($ingress);
        }
    }
}
