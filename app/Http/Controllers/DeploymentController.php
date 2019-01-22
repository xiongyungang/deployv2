<?php

namespace App\Http\Controllers;

use App\Deployment;
use App\Jobs\DeployDeploymentJob;
use App\Repo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeploymentController extends Controller
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
                'page' => 'required|integer|gt:0',
                'limit' => 'required|integer|in:10,20,50',
                'app_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('apps', 'id')->where('appkey', $request->get('appkey')),
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

        $deployments = Deployment::where($validationData)->forPage($page, $perPage)->get();

        return response()->json(['ret' => 1, 'data' => $deployments]);
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
            'app_id' => [
                'required',
                Rule::exists('apps', 'id')->where('appkey', $request->post('appkey')),
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
            'image_url' => 'sometimes|required|string',
            'code_in_image' => 'sometimes|required|integer|in:1',
            'commit' => 'required_without:code_in_image|string',
            'domain' => 'sometimes|required|string',
            'envs' => 'sometimes|required|string',
            'labels' => 'sometimes|required|string',
            'callback_url' => 'sometimes|required|string',
        ]);

        do {
            $name = uniqid('u');
        } while (Deployment::whereName($name)->count() > 0);

        $validationData['name'] = $name;
        $validationData['state'] = config('state.pending');
        $validationData['desired_state'] = config('state.started');

        $deployment = Deployment::create($validationData);

        DeployDeploymentJob::dispatch($deployment);

        return response()->json(['ret' => 1, 'data' => $deployment]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Deployment          $deployment
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Deployment $deployment)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $deployment->app->appkey]);

        $deployment->load(['app', 'repo']);

        return response()->json(['ret' => 1, 'data' => $deployment]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Deployment          $deployment
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Deployment $deployment)
    {
        $validationData = \Validator::validate($request->all(), [
            'appkey' => 'required|in:' . $deployment->app->appkey,
            'app_id' => [
                'sometimes',
                'required',
                Rule::exists('apps', 'id')->where('appkey', $request->post('appkey')),
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
            'image_url' => 'sometimes|required|string',
            'code_in_image' => 'sometimes|required|integer|in:1',
            'commit' => 'sometimes|required_without:code_in_image|string',
            'domain' => 'sometimes|required|string',
            'envs' => 'sometimes|required|string',
            'labels' => 'sometimes|required|string',
            'callback_url' => 'sometimes|required|string',
        ]);

        $deployment->update($validationData);

        return response()->json(['ret' => 1, 'data' => $deployment]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Deployment          $deployment
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function restart(Request $request, Deployment $deployment)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $deployment->app->appkey]);

        if (!in_array($deployment->state, [config('state.started'),config('state.failed'),
            config('state.restarted')])) {
            return response()->json(['ret' => -1, 'msg' => 'Deployment is ' . $deployment->state]);
        }

        $deployment->state = config('state.pending');
        $deployment->desired_state = config('state.restarted');
        $deployment->update();

        DeployDeploymentJob::dispatch($deployment,true);

        return response()->json(['ret' => 1, 'msg' => 'Restart ' . config('state.pending') . '.']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Deployment          $deployment
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Deployment $deployment)
    {
        \Validator::validate($request->all(), ['appkey' => 'required|in:' . $deployment->app->appkey]);

        $deployment->state = config('state.pending');
        $deployment->desired_state = config('state.destroyed');
        $deployment->update();

        DeployDeploymentJob::dispatch($deployment);

        return response()->json(['ret' => 1, 'msg' => 'Destroy ' . config('state.pending') . '.']);
    }
}
