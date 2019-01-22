<?php

namespace App\Http\Controllers;

use App\Cluster;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getAll(Request $request)
    {
        $validationData = \Validator::validate(array_merge(['page' => 1, 'limit' => 10], $request->all()), [
            'appkey' => 'required|string',
            'area' => 'sometimes|required|string',
            'type' => 'sometimes|required|string|in:develop,test,production',
            'page' => 'required|integer|gt:0',
            'limit' => 'required|integer|in:10,20,50',
        ], [
            'limit.in' => 'limit should be one of 10,20,50',
        ]);

        $page = $validationData['page'];
        unset($validationData['page']);

        $perPage = $validationData['limit'];
        unset($validationData['limit']);

        $clusters = Cluster::where($validationData)->forPage($page, $perPage)->get();

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $clusters]);
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
            'name' => 'required|string|unique:clusters,name,NULL,id,appkey,' . $request->post('appkey'),
            'area' => 'required|string',
            'server' => 'required|string',
            'certificate_authority_data' => 'required|string',
            'username' => 'required|string',
            'client_certificate_data' => 'required|string',
            'client_key_data' => 'required|string',
            'type' => 'required|in:develop,test,production',
            'namespace' => 'required|string',
        ]);

        $cluster = Cluster::create($validationData);

        return response()->json(['ret' => 1, 'msg' => 'Succeed', 'data' => $cluster]);
    }

    /**
     * @param Request $request
     * @param Cluster $cluster
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function get(Request $request, Cluster $cluster)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $cluster->appkey],
            ['appkey.in' => 'The Cluster is not owned by token appkey']
        );

        return response()->json(['ret' => 1, 'data' => $cluster]);
    }

    /**
     * @param Request $request
     * @param Cluster $cluster
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Cluster $cluster)
    {
        $validationData = \Validator::validate($request->all(), [
            'appkey' => 'required|in:' . $cluster->appkey,
            'name' => sprintf(
                "sometimes|required|string|unique:clusters,name,%d,id,appkey,%s",
                $cluster->id,
                $cluster->appkey
            ),
            'area' => 'sometimes|required|string',
            'server' => 'sometimes|required|string',
            'certificate_authority_data' => 'sometimes|required|string',
            'username' => 'sometimes|required|string',
            'client_certificate_data' => 'sometimes|required|string',
            'client_key_data' => 'sometimes|required|string',
            'type' => 'sometimes|required|string|in:develop,test,production',
            'namespace' => 'sometimes|required|string',
        ], [
            'appkey.in' => 'The Cluster is not owned by token appkey',
        ]);

        $cluster->update($validationData);

        return response()->json(['ret' => 1, 'data' => $cluster]);
    }

    /**
     * @param Request $request
     * @param Cluster $cluster
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy(Request $request, Cluster $cluster)
    {
        \Validator::validate(
            $request->all(),
            ['appkey' => 'required|in:' . $cluster->appkey],
            ['appkey.in' => 'The Cluster is not owned by token appkey']
        );

        if ($cluster->apps->isNotEmpty() || $cluster->users->isNotEmpty() || $cluster->mysqls->isNotEmpty()) {
            return response()->json(['ret' => -1, 'msg' => 'There are some apps or mysqls or users using the Cluster, can not delete']);
        }

        $cluster->delete();

        return response()->json(['ret' => 1, 'msg' => 'Succeed']);
    }
}
