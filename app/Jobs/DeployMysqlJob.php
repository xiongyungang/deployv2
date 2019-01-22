<?php

namespace App\Jobs;

use App\Mysql;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeployMysqlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Mysql
     */
    protected $mysql;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mysql $mysql)
    {
        $this->mysql = $mysql;
    }


    /**
     * @var \Maclof\Kubernetes\Client
     */
    private $client;

    /**
     * Execute the job.
     * @throws \Exception
     * @return void
     */
    public function handle()
    {

        if ($this->mysql->state == config('state.failed')) {
            \Log::warning("Mysql " . $this->mysql->name . "is failed");
            return;
        }
        $this->client = $this->mysql->cluster->client();

        $this->getMysqlStatus();
        //mysql 已近被删除
        $mysql = Mysql::find($this->mysql->id);
        if (!$mysql) {
            \Log::warning("Mysql " . $this->mysql->name . " has been destroyed");
            return;
        }

        $state = $this->mysql->state;
        $desired_state = $this->mysql->desired_state;

        //执行的job与数据库中的job状态不一致，被另一个job所改变
        if ($mysql->state != $state || $mysql->desired_state != $desired_state) {
            \Log::warning("Mysql " . $this->mysql->name . "'s state or desired_state has been changed");
            return;
        }

        //状态
        if ($state == $desired_state) {
            if ($state == config('state.started')) {
                if (!$this->mysqlRunning()) {
                    //todo:通知失败
                    //获取json
                    $this->mysql->update(['state' => config('state.failed')]);
                    $status = $this->getMysqlStatus();
                    requestAsync_post($this->mysql->callback_url,"mysql",["status"=>$status],
                        $this->mysql->attributesToArray());

                }
                //DeployMysqlJob::dispatch($this->mysql);
            }
            if (($state == config('state.destroyed') && $this->chartExists())) {
                //重新删除
                $this->mysql->update(['state' => config('state.pending')]);
            }
            return;
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
        if ($this->mysqlRunning() && $this->chartExists()) {
            $this->mysql->update(['state' => config('state.started')]);
            //todo:通知成功
            requestAsync_post($this->mysql->callback_url,"mysql",['logs'=>'mysql create'],
                $this->mysql->attributesToArray());
            return;
        }

        $command = ["export KUBECONFIG=" . $this->mysql->cluster->kubeconfigPath()];

        \Log::info("mysqlha chart " . $this->mysql->name . " state " . $this->mysql->state);

        if ($this->mysql->state != config('state.pending')) {
            return;
        }

        \Log::info("mysqlha chart " . $this->mysql->name . " is Pending");

        if (!$this->chartExists()) {
            \Log::info("install mysqlha chart " . $this->mysql->name);
            // install
            array_push($command, sprintf(
                "helm install --namespace %s -n %s -f %s %s",
                $this->mysql->cluster->namespace,
                $this->mysql->name, // release name
                $this->mysql->valuesFile(),
                base_path('charts/mysqlha') // chart path
            ));
            custom_exec(implode(' && ', $command));
        } else {
            //todo:更新应用
        }
    }

    /**
     * @throws \Exception
     */
    private function processDestroyed()
    {
        if (!$this->mysqlRunning() && !$this->chartExists()) {
            $this->mysql->delete();
            return;
        }

        $command = ["export KUBECONFIG=" . $this->mysql->cluster->kubeconfigPath()];

        \Log::info("mysqlha chart " . $this->mysql->name . " state " . $this->mysql->state);

        if ($this->chartExists()) {
            \Log::info("delete mysqlha chart " . $this->mysql->name . " is Exists");
            //删除了十分钟 有问题！
            if ($this->mysql->updated_at < date('Y-m-d H:i:s', time() - 600)) {
                \Log::info("delete mysqlha chart " . $this->mysql->name . " failed");
                $this->mysql->update(['state' => config('state.failed')]);
                //todo 通知 删除失败
                requestAsync_post($this->mysql->callback_url,"mysql",['logs'=>'delete mysql is failed'],
                    $this->mysql->attributesToArray());
            }
            \Log::info("delete mysqlha chart " . $this->mysql->name);

            array_push($command, "helm delete --purge " . $this->mysql->name);

            //helm 2.9.1的bug，statefulset可能无法正常删除
            array_push($command, sprintf(
                "kubectl --kubeconfig %s delete statefulset %s",
                $this->mysql->cluster->kubeconfigPath(),
                $this->mysql->name
            ));
            custom_exec(implode(' && ', $command));
        }
    }

    /**
     * @return bool
     */
    private function mysqlRunning()
    {
        $result = custom_exec(sprintf(
            "kubectl --kubeconfig %s get statefulset %s -o=jsonpath='{.status.readyReplicas}'",
            $this->mysql->cluster->kubeconfigPath(),
            $this->mysql->name
        ));

        if ($result['return_var'] != 0) {
            return false;
        }

        return $result['result'] > 0;
    }

    /**
     * @return bool
     */
    private function chartExists()
    {
        $command = [
            "export KUBECONFIG=" . $this->mysql->cluster->kubeconfigPath(),
            "helm get " . $this->mysql->name,
        ];

        $result = custom_exec(implode(" && ", $command));

        if ($result['return_var'] != 0) {
            return false;
        }
        return true;
    }

    private function getMysqlStatus()
    {
        $result = exec(sprintf(
            "kubectl --kubeconfig %s get statefulset %s -o json ",
            $this->mysql->cluster->kubeconfigPath(),
            $this->mysql->name
        ), $output, $return_var);

        if ($return_var != 0) {
            return [];
        }

        $char = implode("", $output);
        $arr = json_decode($char, true);

        if (isset($arr["status"])) {
            $status = json_encode($arr["status"]);
        }

        return $status;
    }
}
