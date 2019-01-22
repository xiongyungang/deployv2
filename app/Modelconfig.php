<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Mysql
 *
 * @property int                 $id
 * @property int                 $repo_id
 * @property int                 $app_id
 * @property string              $name
 * @property string              $command
 * @property string              $envs
 * @property string              $labels
 * @property string              $state
 * @property string              $commit
 * @property string              $desired_state
 * @property string              $callback_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Repo      $repo
 * @property-read \App\App       $app
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereRepoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig where($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Modelconfig find($value)
 * @mixin \Eloquent
 */
class Modelconfig extends Model
{
    protected $fillable = [
        'name',
        'app_id',
        'repo_id',
        'commit',
        'command',
        'envs',
        'labels',
        'state',
        'desired_state',
        'callback_url'
    ];

    protected $attributes = [
        'envs' => '{}',
        'labels' => '{}',
        'command' => "",
        'commit' => "",
    ];

    public function app()
    {
        return $this->belongsTo('App\App');
    }

    public function repo()
    {
        return $this->belongsTo('App\Repo');
    }
}
