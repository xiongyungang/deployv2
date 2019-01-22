<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Maclof\Kubernetes\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * App\Cluster
 *
 * @property int                                                         $id
 * @property string                                                      $appkey
 * @property string                                                      $name
 * @property string                                                      $area
 * @property string                                                      $server
 * @property string                                                      $namespace
 * @property string                                                      $certificate_authority_data
 * @property string                                                      $username
 * @property string                                                      $client_certificate_data
 * @property string                                                      $client_key_data
 * @property string                                                      $type
 * @property \Carbon\Carbon|null                                         $created_at
 * @property \Carbon\Carbon|null                                         $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Mysql[] $mysqls
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\App[]   $apps
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\User[]  $users
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereAppkey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereCertificateAuthorityData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereClientCertificateData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereClientKeyData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereNamespace($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereServer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cluster whereUsername($value)
 * @mixin \Eloquent
 */
class Cluster extends Model
{
    protected $fillable = [
        'appkey',
        'name',
        'area',
        'server',
        'certificate_authority_data',
        'username',
        'client_certificate_data',
        'client_key_data',
        'type',
        'namespace',
    ];

    public function apps()
    {
        return $this->hasMany('App\App');
    }

    public function users()
    {
        return $this->hasMany('App\User');
    }


    public function mysqls()
    {
        return $this->hasMany('App\Mysql');
    }

    public function kubeconfigPath()
    {
        $kubeconfigPath = '/tmp/kubeconfig_workspace_' . $this->id;

        if (!file_exists($kubeconfigPath)) {
            file_put_contents($kubeconfigPath, $this->kubeconfig());
        }

        return $kubeconfigPath;
    }

    /**
     * @return string
     */
    public function kubeconfig()
    {
        $kubernetes_name = 'kubernetes-cluster-' . $this->id;

        return Yaml::dump([
            'apiVersion' => 'v1',
            'clusters' => [
                [
                    'name' => $kubernetes_name,
                    'cluster' => [
                        'certificate-authority-data' => $this->certificate_authority_data,
                        'server' => $this->server,
                    ],
                ],
            ],
            'contexts' => [
                [
                    'name' => 'default',
                    'context' => [
                        'cluster' => $kubernetes_name,
                        'namespace' => $this->namespace,
                        'user' => $this->username,
                    ],
                ],
            ],
            'current-context' => 'default',
            'kind' => 'Config',
            'preferences' => [],
            'users' => [
                [
                    'name' => $this->username,
                    'user' => [
                        'client-certificate-data' => $this->client_certificate_data,
                        'client-key-data' => $this->client_key_data,
                    ],
                ],
            ],
        ], 10, 2);
    }

    /**
     * @return Client
     */
    public function client()
    {
//        $ca_cert = '/tmp/certificate_authority_data_' . $this->id . '.crt';
//        file_put_contents($ca_cert, base64_decode($this->certificate_authority_data));

        $client_cert = '/tmp/client_certificate_data_' . $this->id . '.crt';
        file_put_contents($client_cert, base64_decode($this->client_certificate_data));

        $client_key = '/tmp/client_key_data_' . $this->id . '.key';
        file_put_contents($client_key, base64_decode($this->client_key_data));

        $client = new Client([
            'master' => $this->server,
            'verify' => false,
            'namespace' => $this->namespace,
            'client_cert' => $client_cert,
            'client_key' => $client_key,
        ]);

        return $client;
    }
}
