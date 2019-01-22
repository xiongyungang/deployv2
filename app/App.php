<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\App
 *
 * @property int                                                             $id
 * @property string                                                          $appkey
 * @property int                                                             $channel
 * @property string                                                          $user_appkey
 * @property string                                                          $git_private_key
 * @property string                                                          $ssh_private_key
 * @property int                                                             $cluster_id
 * @property \Carbon\Carbon|null                                             $created_at
 * @property \Carbon\Carbon|null                                             $updated_at
 * @property-read \App\Cluster                                               $cluster
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Deployment[] $deployments
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Repo[]       $repos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Database[]   $databases
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modelconfig[]  $modelconfigs
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereAppkey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereClusterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereGitPrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereSshPrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereUserAppkey($value)
 * @mixin \Eloquent
 */
class App extends Model
{
    protected $fillable = [
        'appkey',
        'channel',
        'user_appkey',
        'git_private_key',
        'ssh_private_key',
        'cluster_id',
    ];

    public function cluster()
    {
        return $this->belongsTo('App\Cluster');
    }

    public function deployments()
    {
        return $this->hasMany('App\Deployment');
    }

    public function repos()
    {
        return $this->hasMany('App\Repo');
    }

    public function databases()
    {
        return $this->hasMany('App\Database');
    }

    public function modelconfigs()
    {
        return $this->hasMany('App\Modelconfig');
    }
}
