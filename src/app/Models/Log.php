<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Facades\DB;

    class Log extends Model {
        protected $connection = 'ssddb';
        protected $table = 'telegram.logs';
        public    $timestamps = false;
        protected $guarded = [];

        public static function getLastCommands(int $uid): array {
            return array_column(DB::select(
                "with pre as (
                    select
                       trim( e'\t\n\r\ ' from(c.name || ' ' || l.command_params)) as name,
                       l.created_at
                    from telegram.logs l
                    left join telegram.commands c on l.command_id = c.id
                    where l.uid = $uid
                    order by l.created_at desc
                ),
                 post as (
                     select name, max(created_at) from pre
                     group by 1
                     order by 2 desc)

                select name from post
                limit 5;"
            ), 'name');
        }
    }
