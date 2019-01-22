<?php

namespace App\Http\Controllers;

use App\Repo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepoController extends Controller
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
                'channel' => 'sometimes|required|integer|gte:0',
                'type' => 'sometimes|required|in:php-5.6,php-7.0,php-7.1,php-7.2,static',
                'page' => 'required|integer|gt:0',
                'limit' => 'required|integer|in:10,20,50',
            ],
            [
                'limit.in' => 'limit should be one of 10,20,50',
            ]
        );

        $repos = Repo::whereHas('app', function ($query) use ($request) {
            $where = ['appkey' => $request->get('appkey')];
            if ($request->has('channel')) {
                $where['channel'] = $request->get('channel');
            }
            $query->where($where);
        })->forPage($validationData['page'], $validationData['limit'])->get();

        return response()->json([
            'ret' => 1,
            'data' => $repos,
        ]);
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
            'uniqid' => 'required|unique:repos,uniqid,NULL,id,app_id,' . $request->post('app_id'),
            'git_ssh_url' => 'required',
            'type' => 'required|in:php-5.6,php-7.0,php-7.1,php-7.2,static',
        ], [
            'app_id.exists' => 'The App not exists or not created by the token appkey',
            'uniqid.unique' => 'A Repo has same uniqid exists',
        ]);

        $repo = Repo::create($validationData);

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $repo]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Repo                $repo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Repo $repo)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $repo->app->appkey],
            ['appkey.in' => 'The Repo is not created by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $repo]);
    }

    /**
     * @param Request $request
     * @param Repo    $repo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Repo $repo)
    {
        $rules = [
            'appkey' => 'required|in:' . $repo->app->appkey,
            'app_id' => [
                'sometimes',
                'required',
                Rule::exists('apps', 'id')->where('appkey', $repo->app->appkey),
            ],
            'uniqid' => 'sometimes|required',
            'git_ssh_url' => 'sometimes|required',
            'type' => 'sometimes|required|in:php-5.6,php-7.0,php-7.1,php-7.2,static',
        ];

        if ($request->has('app_id') && $request->has('uniqid')) {
            $rules['uniqid'] = 'sometimes|required|unique:repos,uniqid,' . $repo->id . ',id,app_id,' . $request->post('app_id');
        }

        $validationData = \Validator::validate($request->all(), $rules, [
            'app_id.exists' => 'The App not exists or not created by the token appkey',
            'uniqid.unique' => 'A Repo has same uniqid exists in app_id:' . $request->post('app_id'),
        ]);

        $repo->update($validationData);

        return response()->json(['ret' => 1, 'data' => $repo]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Repo                $repo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Repo $repo)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $repo->app->appkey],
            ['appkey.in' => 'The Repo is not created by token appkey']
        );

        if ($repo->workspaces->isNotEmpty() || $repo->deployments->isNotEmpty()||$repo->modelconfigs->isNotEmpty())
        {
            return response()->json([
                'ret' => -1,
                'msg' => 'There are some workspace or deployments related with the repo, can not delete',
            ]);
        }

        $repo->delete();

        return response()->json(['ret' => 1, 'msg' => 'Succeed']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Repo                $repo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getDeployments(Request $request, Repo $repo)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $repo->app->appkey],
            ['appkey.in' => 'The Repo is not created by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $repo->deployments]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Repo                $repo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getWorkspaces(Request $request, Repo $repo)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $repo->app->appkey],
            ['appkey.in' => 'The Repo is not created by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $repo->workspaces]);
    }
}
