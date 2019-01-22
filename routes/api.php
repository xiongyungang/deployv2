<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('clusters', 'ClusterController@getAll');
    Route::post('clusters', 'ClusterController@create');
    Route::get('clusters/{cluster}', 'ClusterController@get');
    Route::put('clusters/{cluster}', 'ClusterController@update');
    Route::delete('clusters/{cluster}', 'ClusterController@destroy');

    Route::get('users', 'UserController@getAll');
    Route::post('users', 'UserController@create');
    Route::get('users/{user}', 'UserController@get');
    Route::put('users/{user}', 'UserController@update');
    Route::delete('users/{user}', 'UserController@destroy');
    Route::get('users/{user}/workspaces', 'UserController@getWorkspaces');

    Route::get('repos', 'RepoController@getAll');
    Route::post('repos', 'RepoController@create');
    Route::get('repos/{repo}', 'RepoController@get');
    Route::put('repos/{repo}', 'RepoController@update');
    Route::delete('repos/{repo}', 'RepoController@destroy');
    Route::get('repos/{repo}/workspaces', 'RepoController@getWorkspaces');
    Route::get('repos/{repo}/deployments', 'RepoController@getDeployments');

    Route::get('apps', 'AppController@getAll');
    Route::post('apps', 'AppController@create');
    Route::get('apps/{app}', 'AppController@get');
    Route::put('apps/{app}', 'AppController@update');
    Route::delete('apps/{app}', 'AppController@destroy');
    Route::get('apps/{app}/deployments', 'AppController@getDeployments');
    Route::get('apps/{app}/repos', 'AppController@getRepos');

    Route::get('deployments', 'DeploymentController@getAll');
    Route::post('deployments', 'DeploymentController@create');
    Route::get('deployments/{deployment}', 'DeploymentController@get');
    Route::put('deployments/{deployment}', 'DeploymentController@update');
    Route::post('deployments/{deployment}/restart', 'DeploymentController@restart');
    Route::post('deployments/{deployment}/destroy', 'DeploymentController@destroy');

    Route::get('workspaces', 'WorkspaceController@getAll');
    Route::post('workspaces', 'WorkspaceController@create');
    Route::get('workspaces/{workspace}', 'WorkspaceController@get');
    Route::put('workspaces/{workspace}', 'WorkspaceController@update');
    Route::post('workspaces/{workspace}/start', 'WorkspaceController@start');
    Route::post('workspaces/{workspace}/stop', 'WorkspaceController@stop');
    Route::post('workspaces/{workspace}/restart', 'WorkspaceController@restart');
    Route::post('workspaces/{workspace}/destroy', 'WorkspaceController@destroy');

    Route::get('mysqls', 'MysqlController@getAll');
    Route::post('mysqls', 'MysqlController@create');
    Route::get('mysqls/{mysql}', 'MysqlController@get');
    Route::put('mysqls/{mysql}', 'MysqlController@update');
    Route::delete('mysqls/{mysql}', 'MysqlController@destroy');

    Route::get('databases', 'DatabasesController@getAll');
    Route::post('databases', 'DatabasesController@create');
    Route::get('databases/{database}', 'DatabasesController@get');
    Route::put('databases/{database}', 'DatabasesController@update');
    Route::post('databases/{database}/restart', 'DatabasesController@restart');
    Route::delete('databases/{database}/destroy', 'DatabasesController@destroy');

    Route::post('modelconfigs', 'ModelconfigController@create');
    Route::put('modelconfigs/{modelconfig}', 'ModelconfigController@update');
    Route::get('modelconfigs', 'ModelconfigController@getAll');
    Route::get('modelconfigs/{modelconfig}', 'ModelconfigController@get');
    Route::delete('modelconfigs/{modelconfig}/destroy', 'ModelconfigController@destroy');
});
