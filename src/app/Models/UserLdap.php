<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    /**
     * LDAP-ботоюзеры в нашей БД
     * @property int $id
     * @property int $uid
     * @property string $tg_full_name
     * @property string $username
     * @property string $ldap_full_name
     * @property array  $mobile
     * @property string $department
     * @property string $subdivision
     * @property string $territory
     * @property bool   $protected
     * @property bool   $is_admin
     * @property bool   $authorized
     * @property bool   $attempt
     * @property int    $last_logon
     * @property bool   $alert
     */
    class UserLdap extends Model {

        protected $connection = 'ssddb';
        protected $table      = 'users_ldap_test';
        protected $primaryKey = 'uid';
        public $incrementing = false;
        public $timestamps = false;
        protected $guarded = [];

        protected $casts = [
            'mobile'     => 'array',
            'authorized' => 'boolean',
            'attempt'    => 'boolean',
            'alert'      => 'boolean',
            'protected'  => 'boolean',
            'is_admin'   => 'boolean',
        ];
    }
