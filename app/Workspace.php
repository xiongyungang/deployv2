<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use phpseclib\Crypt\RSA;
use Symfony\Component\Yaml\Yaml;

/**
 * App\Workspace
 *
 * @property int                 $id
 * @property string              $name
 * @property int                 $user_id
 * @property int                 $repo_id
 * @property string              $image_url
 * @property string              $envs
 * @property string              $labels
 * @property string              $state
 * @property string              $desired_state
 * @property string              $callback_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Repo      $repo
 * @property-read \App\User      $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereEnvs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereRepoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace delete($value)
 * @mixin \Eloquent
 */
class Workspace extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'repo_id',
        'image_url',
        'envs',
        'labels',
        'state',
        'desired_state',
        'callback_url'
    ];

    protected $attributes = [
        'image_url' => '',
        'envs' => '{}',
        'labels' =>'{}',
        'callback_url' =>'',
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function repo()
    {
        return $this->belongsTo('App\Repo');
    }

    public function valuesFile($signal)
    {
        $file = '/tmp/values-workspace-' . $this->id;
        $rsa = new RSA();
        $rsa->loadKey(base64_decode($this->user->ssh_private_key));

        $envs = json_decode($this->envs, true);
        $customEnvs = [];
        foreach ($envs as $k => $v) {
            if (is_int($v)) {
                $v = (string)$v;
            }
            $customEnvs[] = ['name' => $k, 'value' => $v];
        }

        $values = [
            'enabled' => $signal != 'stop',
            'labels' => [
                'appkey' => $this->user->appkey,
                'channel' => $this->user->channel,
                'userAppkey' => $this->repo->app->user_appkey,
            ],
            'customLabels'=>[

            ],
            'ingress' => [
                'hosts' => [$this->name . '-dev.oneitfarm.com'],
                'tls' => [
                    [
                        'secretName' => 'oneitfarm-secret',
                        'hosts' => [$this->name . '-dev.oneitfarm.com'],
                    ],
                ],
            ],
            'envs' => [
                'PROJECT_GIT_URL' => $this->repo->git_ssh_url,
                'GIT_PRIVATE_KEY' => $this->user->git_private_key,
                'SSH_PUBLIC_KEY' => base64_encode($rsa->getPublicKey(RSA::PUBLIC_FORMAT_OPENSSH)),
            ],
            'customEnvs' => $customEnvs,
            'image' => $this->repo->image(),
        ];

        if ($this->image_url != '') {
            $values['image']['repository'] = $this->image_url;
        }
        if($this->labels){
            $customLabels = json_decode($this->labels, true);
            $values['customLabels'] = $customLabels ? :[];
        }
        file_put_contents($file, Yaml::dump($values, 10, 2));

        return $file;
    }
}
