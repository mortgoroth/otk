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
            // l.command_id (int) = c.id (int)
            $query = "
                with pre as (
                    select
                       trim( e'\t\n\r\ ' from(COALESCE(c.name, 'unknown') || ' ' || l.command_params)) as name,
                       l.created_at
                    from telegram.logs l
                    left join telegram.commands c on l.command_id = c.id
                    where l.uid = ?
                    order by l.created_at desc
                ),
                post as (
                    select name, max(created_at) as max_at from pre
                    group by 1
                    order by 2 desc
                )
                select name from post
                limit 5
            ";

            $results = DB::connection('ssddb')->select($query, [$uid]);
            return array_column($results, 'name');
//            return array_column(DB::select(
//                "with pre as (
//                    select
//                       trim( e'\t\n\r\ ' from(c.name || ' ' || l.command_params)) as name,
//                       l.created_at
//                    from telegram.logs l
//                    left join telegram.commands c on l.command_id = c.id
//                    where l.uid = $uid
//                    order by l.created_at desc
//                ),
//                 post as (
//                     select name, max(created_at) from pre
//                     group by 1
//                     order by 2 desc)
//
//                select name from post
//                limit 5;"
//            ), 'name');
        }
    }
