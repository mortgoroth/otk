<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Command extends Model {
        protected $connection = 'ssddb';
        protected $table = 'telegram.commands';
        public $timestamps = false;
        protected $guarded = [];
    }
