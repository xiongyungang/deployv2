<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\User
 *
 * @property int                                                            $id
 * @property string                                                         $appkey
 * @property int                                                            $channel
 * @property string                                                         $account_id
 * @property string                                                         $git_private_key
 * @property string                                                         $ssh_private_key
 * @property int                                                            $cluster_id
 * @property \Carbon\Carbon|null                                            $created_at
 * @property \Carbon\Carbon|null                                            $updated_at
 * @property-read \App\Cluster                                              $cluster
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Workspace[] $workspaces
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAppkey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereClusterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereGitPrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSshPrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Model
{
    protected $fillable = [
        'appkey',
        'channel',
        'account_id',
        'git_private_key',
        'ssh_private_key',
        'cluster_id',
    ];

    public function workspaces()
    {
        return $this->hasMany('App\Workspace');
    }

    public function cluster()
    {
        return $this->belongsTo('App\Cluster');
    }
}
