<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Yaml\Yaml;

/**
 * App\Mysql
 *
 * @property int                    $id
 * @property string                 $appkey
 * @property string                 $uniqid
 * @property int                    $channel
 * @property int                    $cluster_id
 * @property string                 $name
 * @property string                 $host_write
 * @property string                 $host_read
 * @property string                 $username
 * @property string                 $password
 * @property int                    $port
 * @property string                 $labels
 * @property string                 $state
 * @property string                 $desired_state
 * @property string                 $callback_url
 * @property \Carbon\Carbon|null    $created_at
 * @property \Carbon\Carbon|null    $updated_at
 * @property-read \App\Cluster     $cluster
 * @property-read \App\Database[]  $databases
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereUniqid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereUsername($value)
 * @mixin \Eloquent
 */
class Mysql extends Model
{
    protected $fillable = [
        'appkey',
        'uniqid',
        'channel',
        'cluster_id',
        'name',
        'host_write',
        'host_read',
        'username',
        'password',
        'port',
        'labels',
        'state',
        'desired_state',
        'callback_url',
    ];

    protected $attributes = [
        'labels' => '{}',
    ];

    public function cluster()
    {
        return $this->belongsTo('App\Cluster');
    }

    public function databases()
    {
        return $this->hasMany('App\Database');
    }

    public function valuesFile()
    {
        $file = '/tmp/values-mysql-' . $this->id;

        $values = [
            'labels' => [
                'appkey' => $this->appkey,
                'channel' => $this->channel,
                'name' => $this->name,
            ],
            'customLabels'=>[

            ],
            'mysqlha' => [
                'mysqlRootPassword' => $this->password,
                'mysqlUser' => $this->username,
                'mysqlPassword' => $this->password,
                'mysqlDatabase' => $this->name,
            ],
        ];
        if($this->labels){
            $customLabels = json_decode($this->labels, true);
            $values['customLabels'] = $customLabels ? :[];
        }
        file_put_contents($file, Yaml::dump($values, 10, 2));

        return $file;

    }
}
