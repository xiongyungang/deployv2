<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use phpseclib\Crypt\RSA;

class UserController extends Controller
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

        $users = User::where($validationData)->forPage($page, $perPage)->get();

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $users]);
    }

    /**
     * @param Request $request
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
                'appkey' => 'required',
                'channel' => 'required|integer|gte:0',
                'account_id' => sprintf(
                    "required|unique:users,account_id,NULL,id,appkey,%s,channel,%d",
                    $request->post('appkey'),
                    $request->post('channel')
                ),
                'git_private_key' => 'required|string',
                'ssh_private_key' => 'required|string',
                'cluster_id' => [
                    'required',
                    Rule::exists('clusters', 'id')->where('appkey', $request->post('appkey')),
                ],
            ],
            [
                'account_id:unique' => 'An User with same account_id exists',
            ]
        );

        $user = User::create($validationData);

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $user]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, User $user)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $user->appkey],
            ['appkey.in' => 'The User is not owned by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $user]);
    }

    /**
     * @param Request $request
     * @param User    $user
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, User $user)
    {
        $validationData = \Validator::validate(array_merge(['channel' => 0], $request->all()), [
            'appkey' => 'required|in:' . $user->appkey,
            'channel' => 'required|integer|gte:0',
            'account_id' => sprintf(
                "sometimes|required|unique:users,account_id,%d,id,appkey,%s,channel,%d",
                $user->id,
                $user->appkey,
                $request->post('channel')
            ),
            'git_private_key' => 'sometimes|required|string',
            'ssh_private_key' => 'sometimes|required|string',
            'cluster_id' => [
                'sometimes',
                'required',
                Rule::exists('clusters', 'id')->where('appkey', $request->post('appkey')),
            ],
        ], [
            'appkey.in' => 'The User is not owned by token appkey',
            'account_id:unique' => 'An User with same account_id exists',
        ]);

        $user->update($validationData);

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $user]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, User $user)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $user->appkey],
            ['appkey.in' => 'The User is not owned by token appkey']
        );

        if ($user->workspaces->isNotEmpty()) {
            return response()->json(['ret' => -1, 'msg' => 'The User has some workspaces, can not delete']);
        }

        $user->delete();

        return response()->json(['ret' => 1, 'msg' => 'Succeed']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getWorkspaces(Request $request, User $user)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $user->appkey],
            ['appkey.in' => 'The User is not owned by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $user->workspaces]);
    }
}
