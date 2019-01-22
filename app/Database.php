<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Mysql
 *
 * @property int                 $id
 * @property int                 $mysql_id
 * @property int                 $app_id
 * @property string              $name
 * @property string              $databasename
 * @property string              $username
 * @property string              $password
 * @property string              $state
 * @property string              $Labels
 * @property string              $desired_state
 * @property string              $callback_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Mysql    $mysql
 * @property-read \App\App      $app
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereDatabasename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereMysqlId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database where($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Database find($value)
 * @mixin \Eloquent
 */

class Database extends Model
{
    protected $fillable = [
        'mysql_id',
        'app_id',
        'name',
        'databasename',
        'username',
        'password',
        'state',
        'labels',
        'desired_state',
        'callback_url'
    ];
    protected $attributes = [
        'labels' => '{}',
    ];
    public function mysql()
    {
        return $this->belongsTo('App\Mysql');
    }

    public function app()
    {
        return $this->belongsTo('App\App');
    }

}
