<?php
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App {

    /**
     * App\App
     *
     * @property int                                                             $id
     * @property string                                                          $appkey
     * @property int                                                             $channel
     * @property string                                                          $user_appkey
     * @property string                                                          $ssh_private_key
     * @property int                                                             $cluster_id
     * @property \Carbon\Carbon|null                                             $created_at
     * @property \Carbon\Carbon|null                                             $updated_at
     * @property-read \App\Cluster                                               $cluster
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Deployment[] $deployments
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereAppkey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereChannel($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereClusterId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereSshPrivateKey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\App whereUserAppkey($value)
     * @mixin \Eloquent
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Repo[]       $repos
     */
    class App extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\Cluster
     *
     * @property int                                                      $id
     * @property string                                                   $appkey
     * @property string                                                   $name
     * @property string                                                   $area
     * @property string                                                   $server
     * @property string                                                   $certificate_authority_data
     * @property string                                                   $username
     * @property string                                                   $client_certificate_data
     * @property string                                                   $client_key_data
     * @property string                                                   $type
     * @property \Carbon\Carbon|null                                      $created_at
     * @property \Carbon\Carbon|null                                      $updated_at
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\App[] $apps
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereAppkey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereArea($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereCertificateAuthorityData($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereClientCertificateData($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereClientKeyData($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereServer($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereType($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereUsername($value)
     * @mixin \Eloquent
     */
    class Cluster extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\Deployment
     *
     * @property int                 $id
     * @property string              $name
     * @property int                 $app_id
     * @property int                 $repo_id
     * @property string              $image_url
     * @property int                 $code_in_image
     * @property string              $branch
     * @property string              $domain
     * @property string              $envs
     * @property string              $state
     * @property string              $desired_state
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\App       $app
     * @property-read \App\Repo      $repo
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereAppId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereBranch($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereCodeInImage($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereDesiredState($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereDomain($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereEnvs($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereImageUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereRepoId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereState($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Deployment whereUpdatedAt($value)
     * @mixin \Eloquent
     */
    class Deployment extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\Mysql
     *
     * @property int                 $id
     * @property int                 $app_id
     * @property string              $uniqid
     * @property string              $name
     * @property string              $host
     * @property string              $username
     * @property string              $password
     * @property int                 $port
     * @property string              $state
     * @property string              $desire_state
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereAppId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Mysql whereDesireState($value)
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
    class Mysql extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\Repo
     *
     * @property int                                                             $id
     * @property int                                                             $app_id
     * @property string                                                          $uniqid
     * @property string                                                          $git_ssh_url
     * @property string                                                          $git_private_key
     * @property string                                                          $type
     * @property \Carbon\Carbon|null                                             $created_at
     * @property \Carbon\Carbon|null                                             $updated_at
     * @property-read \App\App                                                   $app
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Deployment[] $deployments
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Workspace[]  $workspaces
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereAppId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereGitPrivateKey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereGitSshUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereType($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereUniqid($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Repo whereUpdatedAt($value)
     * @mixin \Eloquent
     */
    class Repo extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\User
     *
     * @property int                                                            $id
     * @property string                                                         $appkey
     * @property int                                                            $channel
     * @property string                                                         $account_id
     * @property string                                                         $ssh_private_key
     * @property \Carbon\Carbon|null                                            $created_at
     * @property \Carbon\Carbon|null                                            $updated_at
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Workspace[] $workspaces
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAccountId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAppkey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereChannel($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSshPrivateKey($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUpdatedAt($value)
     * @mixin \Eloquent
     */
    class User extends \Eloquent
    {
    }
}

namespace App {

    /**
     * App\Workspace
     *
     * @property int                 $id
     * @property string              $name
     * @property int                 $user_id
     * @property int                 $repo_id
     * @property string              $image_url
     * @property string              $envs
     * @property string              $state
     * @property string              $desired_state
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\Repo      $repo
     * @property-read \App\User      $user
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereDesiredState($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereEnvs($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereImageUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereRepoId($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereState($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder|\App\Workspace whereUserId($value)
     * @mixin \Eloquent
     */
    class Workspace extends \Eloquent
    {
    }
}

