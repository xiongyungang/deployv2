<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Modelconfig;
use App\Jobs\DeployModelconfigJob;
class ModelconfigController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */

    public function create(Request $request)
    {
        $validationData=\Validator::validate($request->all(), [
            'app_id' => [
                'required', 'integer',
                Rule::exists('apps', 'id')
            ],
            'repo_id' => [
                'required', 'integer',
                Rule::exists('repos', 'id'),
            ],
            'commit' => "sometimes|required|string",
            'command' => "sometimes|required|string",
            'envs' => "sometimes|required|string",
            'callback_url'=>"sometimes|required|string",
            'labels' => 'sometimes|required|string',
        ]);


        do {
            $name = uniqid();
        } while (Modelconfig::whereName($name)->count() > 0);

        $validationData['name'] = $name;
        $validationData['state'] = config('state.pending');
        $validationData['desired_state'] = config('state.started');
        $modelconfig = Modelconfig::create($validationData);

        //TODO:执行job
        DeployModelconfigJob::dispatch($modelconfig);
        return response()->json(['ret'=>1,'data'=>$modelconfig]);
    }

    /**
     * @param \App\Modelconfig           $modelconfig
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     *
     */
    public function update(Request $request,Modelconfig $modelconfig)
    {
        //TODO:更新名称
        $validationData=\Validator::validate($request->all(), [
            'app_id' => [
                "sometimes",'required', 'integer',
                Rule::exists('apps', 'id')
            ],
            'repo_id' => [
                "sometimes",'required', 'integer',
                Rule::exists('repos', 'id'),
            ],
            'commit' => "sometimes|required|string",
            'command' => "sometimes|required|string",
            'envs' => "sometimes|required|string",
            'callback_url' => 'sometimes|required|string',
            'labels' => 'sometimes|required|string',
        ]);

        if ($modelconfig->state == config('state.pending')) {
            return response()->json([
                'ret' => -1,
                'msg' => 'modelconfig' . $modelconfig->id . ' is Pending to ' . $modelconfig->desired_state,
            ]);
        }
        $validationData['state'] = config('state.pending');
        $validationData['desired_state'] = config('state.restarted');
        $modelconfig->update($validationData);
        //todo:更新job
        DeployModelconfigJob::dispatch($modelconfig,true);
        return response()->json(['ret' => 1, 'data' => $modelconfig]);
    }

    /**
     * @param \App\Modelconfig           $modelconfig
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getAll(Request $request,Modelconfig $modelconfig)
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

        $modelconfigs = Modelconfig::where($validationData)
            ->forPage($page, $perPage)
            ->get();

        return response()->json(['ret' => 1, 'data' => $modelconfigs]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Modelconfig           $modelconfig
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Modelconfig $modelconfig)
    {
        return response()->json(['ret' => 1, 'data' => $modelconfig]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Modelconfig           $modelconfig
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function destroy(Request $request, Modelconfig $modelconfig)
    {
        \Validator::validate($request->all(),
            ['appkey' => 'required|in:' . $modelconfig->app->appkey]);

        if ($modelconfig->state == config('state.pending')) {
            return response()->json([
                'ret' => -1,
                'msg' => 'modelconfig ' . $modelconfig->id . ' is Pending to ' . $modelconfig->desired_state,
            ]);
        }

        $modelconfig->update([
            'state' => config('state.pending'),
            'desired_state' => config('state.destroyed'),
        ]);
        //TODO:删除modelconfig
        DeployModelconfigJob::dispatch($modelconfig);
        return response()->json(['ret' => 1, 'data' => $modelconfig]);
    }
}
