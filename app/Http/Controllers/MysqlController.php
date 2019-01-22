<?php

namespace App\Http\Controllers;

use App\Jobs\DeployMysqlJob;
use App\Mysql;
use App\Cluster;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MysqlController extends Controller
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
                'channel' => [
                    'sometimes',
                    'required'
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

        $mysqls = Mysql::where($validationData)
            ->forPage($page, $perPage)
            ->get();

        return response()->json(['ret' => 1, 'data' => $mysqls]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $validationData = \Validator::validate($request->all(), [
            'appkey' => 'required|string',
            'channel' => 'required|integer',
            'cluster_id' => [
                'required',
                Rule::exists('clusters', 'id'),
            ],
            'uniqid' => "required|unique:mysqls,uniqid",
            'labels' => 'sometimes|required|string',
            'callback_url' => 'sometimes|required|string',
        ]);

        do {
            $name = uniqid('rm-');
        } while (Mysql::whereName($name)->count() > 0);
        $Cluster = Cluster::find($request->post('cluster_id'));
        if (!isset($validationData['labels'])) {
            $validationData['labels'] = '{}';
        }
        $mysql = Mysql::create([
            'cluster_id' => $request->post('cluster_id'),
            'appkey' => $request->post('appkey'),
            'channel' => $request->post('channel'),
            'uniqid' => $request->post('uniqid'),
            'name' => $name,
            'host_write' => $name . "-0." . $name . "." . $Cluster->namespace,
            'host_read' => $name . "-ro." . $Cluster->namespace,
            'username' => $name,
            'password' => str_random(32),
            'port' => 3306,
            'labels' => $validationData['labels'],
            'state' => config('state.pending'),
            'desired_state' => config('state.started'),
            'callback_url' => $request->post('callback_url'),

        ]);

        DeployMysqlJob::dispatch($mysql);

        return response()->json(['ret' => 1, 'data' => $mysql]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Mysql $mysql
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Mysql $mysql)
    {
        $rules = [
            'appkey' => 'sometimes|required',
            'cluster_id' => [
                "sometimes", "required", "integer",
                Rule::exists('clusters', 'id')
            ],
            'uniqid' => [
                'sometimes',
                'required',
                function ($attribute, $value, $fail) {
                    $mysql = Mysql::whereUniqid($value);
                    if ($mysql) {
                        return $fail('uniqid is exist');
                    }
                }
            ],
            'callback_url' => 'sometimes|required|string',
            'labels' => 'sometimes|required|string',
        ];

        $validationData = \Validator::validate($request->all(), $rules);

        unset($validationData['name']);

//        if ($mysql->update($validationData)) {
//            $this->action('upgrade', $mysql);
//        }

        return response()->json(['ret' => 1, 'data' => 'Temporary updates are not supported.']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Mysql $mysql
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Mysql $mysql)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $mysql->appkey]);

        return response()->json(['ret' => 1, 'data' => $mysql]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Mysql $mysql
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Mysql $mysql)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $mysql->appkey]);

        if ($mysql->state == config('state.pending')) {
            return response()->json([
                'ret' => -1,
                'msg' => 'Mysql ' . $mysql->id . ' is Pending to ' . $mysql->desired_state,
            ]);
        }
        if ($mysql->databases->isNotEmpty()) {
            return response()->json([
                'ret' => -1,
                'msg' => 'There are some mysqls related with the repo, can not delete',
            ]);
        }
        $mysql->update([
            'state' => config('state.pending'),
            'desired_state' => config('state.destroyed'),
        ]);

        DeployMysqlJob::dispatch($mysql);

        return response()->json(['ret' => 1, 'data' => $mysql]);
    }
}
