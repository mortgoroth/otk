<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Log extends Model {
        protected $connection = 'ssddb';
        protected $table = 'telegram.logs';
        public $timestamps = false;
        protected $guarded = [];
    }
