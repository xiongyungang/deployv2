<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Maclof\Kubernetes\Models\DeleteOptions;
use Maclof\Kubernetes\Models\Job;
use App\Database;

class DeployDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var \Maclof\Kubernetes\Client
     */
    private $client;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {

        if($this->database->state==config('state.failed')){
            \Log::warning("Database ".$this->database->name . "is failed");
            return;
        }

        //失败次数大于3次释放任务,非立马删除，删除队列中任务延时
        if ($this->attempts() > 3) {
            \Log::warning("deployDatabaseJob : ".$this->database->name."Failure is greater than 3 times begins to delete.");
            $this->delete();
        }

        $this->client = $this->database->app->cluster->client();

        $database = Database::find($this->database->id);
        if (!$database) {
            \Log::warning('Database ' . $this->database->name . " has been destroyed");
            return;
        }

        $state = $this->database->state;
        $desired_state = $this->database->desired_state;

        // job 任务发生改变状态
        if ($state != $database->state || $desired_state != $database->desired_state) {
            \Log::warning("Database " . $database->name . "'s state or desired_state has been changed");
            return;
        }

        if($state == $desired_state){
            if($state==config('state.started')&&$this->jobCompleted()&&$this->jobAnnotations()=='CREATE'){
                return;
            }
            if($state==config('state.destroyed')){
                if($this->jobCompleted()&&$this->jobAnnotations()=='DELETE'){
                    //删除job，删除记录
                    $this->tryDeleteJob();
                    $this->database->delete();
                    return;
                }elseif ($this->jobAnnotations()=='CREATE'){
                    $this->database->update(['state' => config('state.pending')]);
                    return;
                }
            }
        }

        if ($state != config('state.pending')) {
            return;
        }

        \Log::warning("start do database ".$desired_state);

        //如过没有完成,继续完成
        $job=$this->getJob();
        if($job){
            $status = $job->toArray()['status'];
            if (!$this->jobCompleted()){
                if(isset($status['failed'])){
                    $this->database->update(['state' => config('state.failed')]);
                    //todo:失败通知
                    requestAsync_post($this->database->callback_url,
                        "database",["status" => $job->toArray()['status']],
                        $this->database->attributesToArray());
                }
                return;
            }
        }

        switch ($desired_state) {
            case config('state.started'):
                $this->processStarted();
                break;
            case config('state.destroyed'):
                $this->processDestroyed();
                break;
        }

    }

    private function processStarted()
    {
        \Log::warning("start creat database");
        if($this->jobCompleted()&&$this->jobAnnotations()=='CREATE'){
            //通知成功
            $this->database->update(['state' => $this->database->desired_state]);
            //todo:成功通知
            requestAsync_post($this->database->callback_url,"database",['logs'=>'mysql create success'],
                $this->database->attributesToArray());
            return;
        }

        $this->tryDeleteJob();

        $image = 'registry.cn-hangzhou.aliyuncs.com/deployv2/database:mysql-8.0';

        $yaml = [
            'metadata' => [
                'name' => $this->database->name,
                'labels' => $this->commonLabels(),
                //ToDo:优化
                'annotations' => ['MYSQL_DATABASE_SCRIPT_TYPE' => "CREATE"],
            ],
            'spec' => [
                'template' => [
                    'metadata' => [
                        'name' => $this->database->name,
                    ],
                    'spec' => [
                        'containers' => [
                            [
                                'name' => $this->database->name,
                                'image' => $image,
                                'env' => $this->commonEnvs('CREATE'),
                            ],
                        ],
                        'restartPolicy' => 'Never',
                    ],
                ],
                'backoffLimit' => 1,
            ],
        ];
        $job = new Job($yaml);

        $this->client->jobs()->create($job);

        \Log::warning("complete creat database");
    }

    /**
     * @throws \Exception
     */
    private function processDestroyed()
    {
        \Log::warning("start delete database");
        if($this->jobCompleted()&&$this->jobAnnotations()=='DELETE') {
            $this->database->update(['state' => $this->database->desired_state]);
            //删除job，删除记录
            return;
        }
        $this->tryDeleteJob();

        $image = 'registry.cn-hangzhou.aliyuncs.com/deployv2/database:mysql-8.0';

        $yaml = [
            'metadata' => [
                'name' => $this->database->name,
                'labels' => $this->commonLabels(),
                //ToDo:优化
                'annotations' => ['MYSQL_DATABASE_SCRIPT_TYPE' => "DELETE"],
            ],
            'spec' => [
                'template' => [
                    'metadata' => [
                        'name' => $this->database->name,
                    ],
                    'spec' => [
                        'containers' => [
                            [
                                'name' => $this->database->name,
                                'image' => $image,
                                'env' => $this->commonEnvs('DELETE'),
                            ],
                        ],
                        'restartPolicy' => 'Never',
                    ],
                ],
                'backoffLimit' => 1,
            ],
        ];
        $job = new Job($yaml);

        $this->client->jobs()->create($job);


        \Log::warning("complete delete database");

    }

    private function commonLabels()
    {
        $labels = [
            'app' => $this->database->name,
            'appkey' => $this->database->app->appkey,
            'channel' => '' . $this->database->mysql->channel,
            'userAppkey' => $this->database->app->user_appkey,
        ];
        if($this->database->labels){
            $customLabels = json_decode($this->database->labels, true);
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

    private function commonEnvs($type)
    {
        $envs = [
            [
                'name' => 'MYSQL_HOST',
                'value' => $this->database->mysql->host_write,
            ],
            [
                'name' => 'MYSQL_DATABASE_SCRIPT_TYPE',
                'value' => $type,
            ],
            [
                'name' => 'MYSQL_ROOT_PASSWORD',
                'value' => $this->database->mysql->password,
            ],
            [
                'name' => 'MYSQL_DATABASE_NAME',
                'value' => $this->database->databasename,
            ],
            [
                'name' => 'MYSQL_PROT',
                'value' => strval($this->database->mysql->port),
            ],
            [
                'name' => 'USERNAME',
                'value' => 'root',
            ],
        ];
        return $envs;
    }

    private function tryDeleteJob()
    {
        try {
            if ($this->client->jobs()->exists($this->database->name)) {
                $this->client->jobs()->deleteByName(
                    $this->database->name,
                    new DeleteOptions(['propagationPolicy' => 'Background'])
                );
            }
        } catch (\Exception $exception) {
            \Log::warning($exception->getMessage());
        }
    }

    private function jobCompleted(){
        $job = $this->getJob();

        if ($job) {

            $status = $job->toArray()['status'];

            if (isset($status['succeeded']) && $status['succeeded'] == 1) {
                return true;
            }
            if (strtotime($status['startTime']) < time() - 600) {
                \Log::warning('job ' . $this->database->name . ' over 10m, auto delete');
                $this->tryDeleteJob(); //10分钟的任务自动删除
            }
        }

        return false;
    }

    /**
     * @return string|null
     */
    private function jobAnnotations()
    {
        $job = $this->getJob();

        if ($job) {
            $type = $job->toArray()['metadata']['annotations']['MYSQL_DATABASE_SCRIPT_TYPE'];
            return $type;
        }
        return null;
    }

    /**
     * @return \Maclof\Kubernetes\Models\Job|null
     */
    private function getJob()
    {
        try {
            if (!$this->client->jobs()->exists($this->database->name)) {
                return null;
            }
            return $this->client->jobs()
                ->setLabelSelector(['app' => $this->database->name])
                ->first();
        } catch (\Exception $exception) {
            return null;
        }
    }
    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        // 发送失败通知, etc...
    }


}
