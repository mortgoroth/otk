<?php

    namespace App\Models;

//    use App\Http\Controllers\DebugController;
    use Exception;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Validation\ValidationException;

    /**
     * LDAP-ботоюзеры в нашей БД
     */
    class UserLdap extends Model {

        protected $connection = 'ssddb';
        protected $table      = 'telegram.users_ldap_test';
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
