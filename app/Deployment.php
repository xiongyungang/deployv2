<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Deployment
 *
 * @property int                 $id
 * @property string              $name
 * @property int                 $app_id
 * @property int                 $repo_id
 * @property string              $image_url
 * @property int                 $code_in_image
 * @property string              $commit
 * @property string              $domain
 * @property string              $envs
 * @property string              $labels
 * @property string              $state
 * @property string              $desired_state
 * @property string              $callback_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\App       $app
 * @property-read \App\Repo      $repo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereCodeInImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereCommit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereEnvs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereRepoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Deployment extends Model
{
    protected $fillable = [
        'name',
        'app_id',
        'repo_id',
        'commit',
        'image_url',
        'code_in_image',
        'domain',
        'envs',
        'labels',
        'state',
        'desired_state',
        'callback_url'
    ];

    protected $attributes = [
        'image_url' => '',
        'envs' => '{}',
        'labels' => '{}',
        'domain' => '',
        'code_in_image' => 0,
        'callback_url' =>""
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
