<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mysql;
use App\Database;
use App\Jobs\DeployDatabaseJob;

class DatabasesController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $validator = \Validator::validate($request->all(), [
            'app_id' => [
                'required', 'integer',
                Rule::exists('apps', 'id')
            ],
            'mysql_id' => [
                'required', 'integer',
                Rule::exists('mysqls', 'id')
                    ->where('state',config('state.started')),
            ],
            'databasename' => "sometimes|required|unique:databases,databasename",
            'callback_url' =>'required|string',
            'labels' => 'sometimes|required|string',
        ]);

        do {
            $name = uniqid();
        } while (Database::whereName($name)->count() > 0);

        //Todo:用户可设置数据库名称
        if (array_key_exists('databasename', $request->all())) {
            $databasename = $request->post('databasename');
        } else {
            do {
                $databasename = uniqid();
            } while (Database::whereDatabasename($databasename)->count() > 0);
        }
        if (!isset($validator['labels'])) {
            $validator['labels'] = '{}';
        }
        $Mysql = Mysql::find($request->post('mysql_id'));
        $database = Database::create([
            'app_id' => $request->post('app_id'),
            'mysql_id' => $request->post('mysql_id'),
            'name' => $name,
            'databasename' => $databasename,
            //TODO:后续自动生成,设置用户权限与密码
            'username' => 'root',
            'password' =>$Mysql->password,
            'state' => config('state.pending'),
            'labels' => $validator['labels'],
            'desired_state' => config('state.started'),
            'callback_url' => $request->post('callback_url')
        ]);

        //TODO:执行job
        DeployDatabaseJob::dispatch($database);
        return response()->json(['ret'=>1,'data'=>$database]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Database            $database
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function restart(Request $request, Database $database)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $database->app->appkey]);

        if($database->state!=config('state.started')||$database->mysql->state !=config('state.started')){
            return response()->json([
                'ret' => -1,
                'msg' => 'Database ' . $database->id . ' state is not Started or mysql stete is not Started ',
            ]);
        }

        $database->update([
            'state' => config('state.pending'),
            'desired_state' => config('state.started'),
        ]);

        //TODO:重启任务
        DeployDatabaseJob::dispatch($database);
        return response()->json(['ret' => 1, 'data' => $database]);
    }
    /**
     *
     *
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function update()
    {
        //TODO:更新名称
        return response()->json(['ret' => 1, 'data' => 'Temporary updates are not supported.']);
    }

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
                    Rule::exists('apps', 'id'),
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

        $databases = Database::where($validationData)
            ->forPage($page, $perPage)
            ->get();

        return response()->json(['ret' => 1, 'data' => $databases]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Database            $database
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Database $database)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $database->app->appkey]);
        $database->mysql;
        return response()->json(['ret' => 1, 'data' => $database]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Database            $database
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Database $database)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $database->app->appkey]);

        if ($database->state == config('state.pending')) {
            return response()->json([
                'ret' => -1,
                'msg' => 'Database ' . $database->id . ' is Pending to ' . $database->desired_state,
            ]);
        }

        $database->update([
            'state' => config('state.pending'),
            'desired_state' => config('state.destroyed'),
        ]);
        DeployDatabaseJob::dispatch($database);
        //TODO:删除database
        return response()->json(['ret' => 1, 'data' => $database]);
    }

}
