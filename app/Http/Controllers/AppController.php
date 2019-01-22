<?php

namespace App\Http\Controllers;

use App\App;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use phpseclib\Crypt\RSA;

class AppController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getAll(Request $request)
    {
        $validationData = \Validator::validate(
            array_merge(['page' => 1, 'limit' => 10, 'channel' => 0], $request->all()),
            [
                'appkey' => 'required|string',
                'channel' => 'required|integer|gte:0',
                'page' => 'required|integer|gt:0',
                'limit' => 'required|integer|in:10,20,50',
            ],
            [
                'limit.in' => 'limit should be one of 10,20,50',
            ]
        );

        $page = $validationData['page'];
        unset($validationData['page']);

        $perPage = $validationData['limit'];
        unset($validationData['limit']);

        $apps = App::where($validationData)->forPage($page, $perPage)->get();

        return response()->json(['ret' => 1, 'data' => $apps]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $validationData = \Validator::validate(
            array_merge([
                'channel' => 0,
                'ssh_private_key' => base64_encode(
                    str_replace("\r\n", "\n", (new RSA())->createKey(2048)['privatekey'])
                ),
            ], $request->all()),
            [
                'appkey' => 'required|string',
                'channel' => 'required|integer|gte:0',
                'user_appkey' => sprintf(
                    "required|string|unique:apps,user_appkey,NULL,id,appkey,%s,channel,%d",
                    $request->post('appkey'),
                    $request->post('channel')
                ),
                'cluster_id' => [
                    'required',
                    Rule::exists('clusters', 'id')->where('appkey', $request->post('appkey')),
                ],
                'git_private_key' => 'required|string',
                'ssh_private_key' => 'required|string',
            ],
            [
                'user_appkey.unique' => 'An App with same user_appkey exists',
                'cluster_id.exists' => 'The Cluster not exists or not created by token appkey',
            ]
        );

        $app = App::create($validationData);

        return response()->json([
            'ret' => 1,
            'msg' => 'Succeed',
            'data' => $app,
        ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\App                 $app
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, App $app)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $app->appkey],
            ['appkey.in' => 'The App is not owned by token appkey']
        );

        $app->load('cluster');
        return response()->json(['ret' => 1, 'data' => $app]);
    }

    /**
     * @param Request $request
     * @param App     $app
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, App $app)
    {
        $validationData = \Validator::validate(array_merge(['channel' => 0], $request->all()), [
            'appkey' => 'required|in:' . $app->appkey,
            'channel' => 'required|integer|gte:0',
            'user_appkey' => sprintf(
                "sometimes|required|string|unique:apps,user_appkey,%d,id,appkey,%s,channel,%d",
                $app->id,
                $app->appkey,
                $request->post('channel')
            ),
            'cluster_id' => [
                'sometimes',
                'required',
                Rule::exists('clusters', 'id')->where('appkey', $app->appkey),
            ],
            'git_private_key' => 'sometimes|required|string',
            'ssh_private_key' => 'sometimes|required|string',
        ], [
            'appkey.in' => 'The App is not owned by token appkey',
            'user_appkey.unique' => 'An App with same user_appkey exists',
            'cluster_id.exists' => 'The Cluster not exists or not created by token appkey',
        ]);

        $app->update($validationData);

        return response()->json(['ret' => 1, 'data' => $app]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\App                 $app
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, App $app)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $app->appkey],
            ['appkey.in' => 'The App is not owned by token appkey']
        );

        if ($app->deployments->isNotEmpty() || $app->repos->isNotEmpty()||$app->modelconfigs->isNotEmpty()||$app->databases->isNotEmpty()) {
            return response()->json([
                'ret' => -1,
                'msg' => 'There are some deployments or repos or modelconfigs or databases related with the app, can not delete',
            ]);
        }

        $app->delete();

        return response()->json(['ret' => 1, 'msg' => 'Succeed']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\App                 $app
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getDeployments(Request $request, App $app)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $app->appkey],
            ['appkey.in' => 'The App is not created by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $app->deployments]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\App                 $app
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getRepos(Request $request, App $app)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $app->appkey],
            ['appkey.in' => 'The App is not created by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $app->repos]);
    }
}
