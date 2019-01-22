<?php

namespace App\Http\Controllers;

use App\Jobs\DeployWorkspaceJob;
use App\Repo;
use App\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getAll(Request $request)
    {
        $validationData = \Validator::validate(
            array_merge(['page' => 1, 'limit' => 10], $request->all()),
            [
                'appkey' => 'required|string',
                'page' => 'required|integer|gt:0',
                'limit' => 'required|integer|in:10,20,50',
                'user_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('users', 'id')->where('appkey', $request->get('appkey')),
                ],
                'repo_id' => [
                    'sometimes',
                    'required',
                    function ($attribute, $value, $fail) {
                        $repo = Repo::find($value);
                        if (!$repo) {
                            return $fail('Repo not exist');
                        }
                        if ($repo->app->appkey != \request('appkey')) {
                            return $fail('The Repo is not created by token appkey');
                        }
                    },
                ],
            ],
            [
                'limit.in' => 'limit should be one of 10,20,50',
            ]
        );

        $page = $validationData['page'];
        $perPage = $validationData['limit'];
        unset($validationData['page']);
        unset($validationData['limit']);
        unset($validationData['appkey']);

        $workspaces = Workspace::where($validationData)->forPage($page, $perPage)->get();

        return response()->json(['ret' => 1, 'data' => $workspaces]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $validationData = \Validator::validate($request->all(), [
            'appkey' => 'required|string',
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('appkey', $request->get('appkey')),
            ],
            'repo_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $repo = Repo::find($value);
                    if (!$repo) {
                        return $fail('Repo not exist');
                    }
                    if ($repo->app->appkey != \request('appkey')) {
                        return $fail('The Repo is not created by token appkey');
                    }
                },
            ],
            'image_url' => 'sometimes|required',
            'envs' => 'sometimes|required',
            'callback_url' => 'sometimes|required|string',
            'labels' => 'sometimes|required|string',
        ]);

        do {
            $name = uniqid('w');
        } while (Workspace::whereName($name)->count() > 0);

        $validationData['name'] = $name;
        $validationData['state'] = config('state.pending');
        $validationData['desired_state'] = config('state.started');

        $workspace = Workspace::create($validationData);

        DeployWorkspaceJob::dispatch($workspace);

        return response()->json(['ret' => 1, 'data' => $workspace]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Workspace $workspace)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $workspace->user->appkey,]);

        return response()->json(['ret' => 1, 'data' => $workspace]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Workspace $workspace)
    {
        if ($workspace->desired_state == config('state.destroyed')) {
            return response()->json(['ret' => -1, 'msg' => 'Workspace Destroyed']);
        }

        $validationData = \Validator::validate($request->all(), [
            'appkey' => 'required|in:' . $workspace->user->appkey,
            'user_id' => [
                'sometimes',
                'required',
                Rule::exists('users', 'id')->where('appkey', $workspace->user->appkey),
            ],
            'repo_id' => [
                'sometimes',
                'required',
                function ($attribute, $value, $fail) {
                    $repo = Repo::find($value);
                    if (!$repo) {
                        return $fail('Repo not exist');
                    }
                    if ($repo->app->appkey != \request('appkey')) {
                        return $fail('The Repo is not created by token appkey');
                    }
                },
            ],
            'image_url' => 'sometimes|required',
            'envs' => 'sometimes|required',
            'labels' => 'sometimes|required|string',
            'callback_url' => 'sometimes|required|string',
        ]);

        $workspace->update($validationData);

        return response()->json(['ret' => 1, 'data' => $workspace, 'request' => $request->all()]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function start(Request $request, Workspace $workspace)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $workspace->user->appkey]);

        if ($workspace->state != config('state.stopped') && $workspace->state != config('state.destroyed')) {
            return response()->json(['ret' => -1, 'msg' => 'Workspace is ' . $workspace->state]);
        }

        $workspace->state = config('state.pending');
        $workspace->desired_state = config('state.started');
        $workspace->update();

        DeployWorkspaceJob::dispatch($workspace);

        return response()->json(['ret' => 1, 'data' => $workspace]);

        /*if ($workspace->state == 'Created') {
            $command = implode(";", [
                "export KUBECONFIG=" . $workspace->repo->app->cluster->kubeconfigPath(),
                sprintf(
                    "helm install --namespace itfarm -n %s -f %s %s",
                    $workspace->name, // release name
                    $workspace->valuesFile('start'),
                    base_path('charts/workspace') // chart path
                ),
            ]);
        } else {
            $command = implode(";", [
                "export KUBECONFIG=" . $workspace->repo->app->cluster->kubeconfigPath(),
                sprintf(
                    "helm upgrade --namespace itfarm --reset-values -f %s %s %s",
                    $workspace->valuesFile('start'),
                    $workspace->name, // release name
                    base_path('charts/workspace') // chart path
                ),
            ]);
        }

        $result = exec($command, $ouput, $return);

        $workspace->state = 'Started'; // TODO: must be Pending
        $workspace->desired_state = 'Started';
        $workspace->update();

        return response()->json([
            'ret' => 1,
            'msg' => 'Start succeed.',
            'result' => $result,
            'output' => $ouput,
            'return' => $return,
        ]);*/
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function stop(Request $request, Workspace $workspace)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $workspace->user->appkey,]);

        if ($workspace->state != config("state.started") && $workspace->state != config("state.restarted")) {
            return response()->json(['ret' => -1, 'msg' => 'Workspace ' . $workspace->state]);
        }

        $workspace->state = config('state.pending');
        $workspace->desired_state = config('state.stopped');
        $workspace->update();

        DeployWorkspaceJob::dispatch($workspace);

        return response()->json(['ret' => 1, 'data' => $workspace]);

        /*$command = implode(";", [
            "export KUBECONFIG=" . $workspace->repo->app->cluster->kubeconfigPath(),
            sprintf(
                "helm upgrade --namespace itfarm --reset-values -f %s %s %s",
                $workspace->valuesFile('stop'),
                $workspace->name, // release name
                base_path('charts/workspace') // chart path
            ),
        ]);

        $result = exec($command, $ouput, $return);


        return response()->json([
            'ret' => 1,
            'msg' => 'Stop succeed.',
            'result' => $result,
            'output' => $ouput,
            'return' => $return,
        ]);*/
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function restart(Request $request, Workspace $workspace)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $workspace->user->appkey,]);

        if ($workspace->state != config("state.started")) {
            return response()->json(['ret' => -1, 'msg' => 'Workspace ' . $workspace->state]);
        }

        $workspace->state = config('state.pending');
        $workspace->desired_state = config('state.restarted');
        $workspace->update();

        DeployWorkspaceJob::dispatch($workspace);

        return response()->json(['ret' => 1, 'data' => $workspace]);

        /*$command = implode(";", [
            "export KUBECONFIG=" . $workspace->repo->app->cluster->kubeconfigPath(),
            sprintf(
                "helm upgrade --namespace itfarm --reset-values -f %s %s %s",
                $workspace->valuesFile('restart'),
                $workspace->name, // release name
                base_path('charts/workspace') // chart path
            ),
        ]);

        $result = exec($command, $ouput, $return);

        return response()->json([
            'ret' => 1,
            'msg' => 'Restart Succeed.',
            'result' => $result,
            'output' => $ouput,
            'return' => $return,
        ]);*/
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Workspace           $workspace
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Workspace $workspace)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $workspace->user->appkey,]);

        if (!in_array($workspace->state,
            [config('state.started'), config('state.restarted'), config('state.stopped')])) {
            return response()->json(['ret' => 1, 'msg' => 'Workspace is ' . $workspace->state]);
        }

        $workspace->state = config('state.pending');
        $workspace->desired_state = config('state.destroyed');
        $workspace->update();

        DeployWorkspaceJob::dispatch($workspace);

        return response()->json(['ret' => 1, 'data' => $workspace]);

        /*$command = implode(";", [
            "export KUBECONFIG=" . $workspace->repo->app->cluster->kubeconfigPath(),
            "helm delete --purge " . $workspace->name,
        ]);

        $result = exec($command, $ouput, $return);

        return response()->json([
            'ret' => 1,
            'msg' => 'Destroy succeed.',
            'result' => $result,
            'output' => $ouput,
            'return' => $return,
        ]);*/
    }
}
