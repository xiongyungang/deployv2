<?php

namespace App\Jobs;

use App\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeployWorkspaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Workspace
     */
    protected $workspace;

    /**
     * @var \Maclof\Kubernetes\Client
     */
    private $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Workspace $workspace)
    {
        $this->workspace = $workspace;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $this->client = $this->workspace->user->cluster->client();

        $state = $this->workspace->state;
        $desired_state = $this->workspace->desired_state;

        $workspace = Workspace::find($this->workspace->id);

        if (!$workspace) {
            \Log::warning("workspace " . $this->workspace->name . " has been destroyed");
            return;
        }

        // job 任务发生改变状态
        if ($state != $workspace->state || $desired_state != $workspace->desired_state) {
            \Log::warning("Workspace " . $workspace->name . "'s state or desired_state has been changed");
            return;
        }

        if ($state == $desired_state && in_array($state,
                [config('state.started'), config('state.stopped'), config('state.destroyed'),
                    config('state.restarted')])) {
            //K8S并没有restarted状态，只有started状态
            if($state==config('state.restarted')){
                $state = config('state.started');
            }
            if ($state != $this->workspaceState()) {
                $this->workspace->update(['state' => config('state.pending')]);
               return;
            }
            if($this->workspaceState()==config('state.destroyed')&&
                $workspace->state==config('state.destroyed') && $workspace->desired_state== $workspace->desired_state) {
                $this->workspace->delete();
                return;
            }
            requestAsync_post($this->workspace->callback_url, "workspace",["logs"=>"deployment success"],
                $this->workspace->attributesToArray());
            return;
        }

        switch ($desired_state) {
            case config('state.started'):
            case config('state.restarted'):
                $this->processStarted();
                break;
            case config('state.stopped'):
                $this->processStopped();
                break;
            case config('state.destroyed'):
                $this->processDestroyed();
                break;
        }
    }

    private function processStarted()
    {
        \Log::info("start processStarted :".$this->workspace->name." ; state :".$this->workspace->state);

        if($this->chartExists()){
            $command = implode(";", [
                "export KUBECONFIG=" . $this->workspace->user->cluster->kubeconfigPath(),
                sprintf(
                    "helm upgrade --namespace %s --reset-values -f %s %s %s",
                    $this->workspace->user->cluster->namespace,
                    $this->workspace->valuesFile('start'),
                    $this->workspace->name, // release name
                    base_path('charts/workspace') // chart path
                ),
            ]);
        }else{
            $command = implode(";", [
                "export KUBECONFIG=" . $this->workspace->user->cluster->kubeconfigPath(),
                sprintf(
                    "helm install --namespace %s -n %s -f %s %s",
                    $this->workspace->user->cluster->namespace,
                    $this->workspace->name, // release name
                    $this->workspace->valuesFile('start'),
                    base_path('charts/workspace') // chart path
                ),
            ]);
        }

        $result = custom_exec($command);
        if ($result['return_var'] != 0) {
            \Log::warning("processStarted :".$this->workspace->name." processStarted is faild");
            // TODO:通知
            return;
        }
        $this->workspace->update(['state' => $this->workspace->desired_state]);
    }

    private function processStopped()
    {
        \Log::info("start processStopped :".$this->workspace->name." ; state :".$this->workspace->state);
        $command = implode(";", [
            "export KUBECONFIG=" . $this->workspace->user->cluster->kubeconfigPath(),
            sprintf(
                "helm upgrade --namespace %s --reset-values -f %s %s %s",
                $this->workspace->user->cluster->namespace,
                $this->workspace->valuesFile('stop'),
                $this->workspace->name, // release name
                base_path('charts/workspace') // chart path
            ),
        ]);
        $result = custom_exec($command);
        if ($result['return_var'] != 0) {
            \Log::warning("processStopped :".$this->workspace->name." processStopped is faild");
            return;
        }
        $this->workspace->update(['state' => config('state.stopped')]);
    }

    private function processDestroyed()
    {
        \Log::info("start processDestroyed :".$this->workspace->name." ; state :".$this->workspace->state);
        $command = implode(";", [
            "export KUBECONFIG=" . $this->workspace->repo->app->cluster->kubeconfigPath(),
            "helm delete --purge " . $this->workspace->name,
        ]);
        $result = custom_exec($command);
        if ($result['return_var'] != 0) {
            \Log::warning("processDestroyed :".$this->workspace->name." processDestroyed is faild");
            return;
        }
        $this->workspace->update(['state' => config('state.destroyed')]);

    }

    /**
     * @return bool
     */
    private function workspaceState()
    {
        $result = custom_exec(sprintf(
            "kubectl --kubeconfig %s get pvc %s -o=jsonpath='{.status.phase}' --namespace=%s",
            $this->workspace->user->cluster->kubeconfigPath(),
            $this->workspace->name,
            $this->workspace->user->cluster->namespace
        ));

        if ($result['return_var'] != 0 || $result['result'] != 'Bound') {
            return config('state.destroyed');
        }

        $result = custom_exec(sprintf(
            "kubectl --kubeconfig %s get deployment %s -o=jsonpath='{.status.readyReplicas}' --namespace=%s",
            $this->workspace->user->cluster->kubeconfigPath(),
            $this->workspace->name,
            $this->workspace->user->cluster->namespace
        ));

        if ($result['return_var'] != 0) {
            return config('state.stopped');
        }

        return config('state.started');
    }

    /**
     * @return bool
     */
    private function chartExists()
    {
        $command = [
            "export KUBECONFIG=" . $this->workspace->user->cluster->kubeconfigPath(),
            "helm get " . $this->workspace->name,
        ];

        $result = custom_exec(implode(" && ", $command));
        if ($result['return_var'] != 0) {
            return false;
        }
        return true;
    }

}
