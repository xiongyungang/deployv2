<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Repo
 *
 * @property int                                                             $id
 * @property int                                                             $app_id
 * @property string                                                          $uniqid
 * @property string                                                          $git_ssh_url
 * @property string                                                          $type
 * @property \Carbon\Carbon|null                                             $created_at
 * @property \Carbon\Carbon|null                                             $updated_at
 * @property-read \App\App                                                   $app
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Deployment[] $deployments
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Workspace[]  $workspaces
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modelconfig[]$modelconfigs
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereGitSshUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereUniqid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Repo extends Model
{
    protected $fillable = [
        'app_id',
        'uniqid',
        'git_ssh_url',
        'type',
    ];

    public function app()
    {
        return $this->belongsTo('App\App');
    }

    public function deployments()
    {
        return $this->hasMany('App\Deployment');
    }

    public function workspaces()
    {
        return $this->hasMany('App\Workspace');
    }
    public function modelconfigs()
    {
        return $this->hasMany('App\Modelconfig');
    }

    public function image()
    {
        switch ($this->type) {
            case 'php-5.6':
            case 'php-7.0':
            case 'php-7.1':
            case 'php-7.2':
                return [
                    'repository' => 'itfarm/lnmp:' . $this->type,
                    'workspaceRepository' => 'itfarm/workspace:' . $this->type,
                ];
            case 'static':
                return [
                    'repository' => 'itfarm/openresty',
                ];
        }
    }
}
