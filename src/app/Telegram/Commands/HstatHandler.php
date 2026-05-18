<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Http\Controllers\UserController;

    // Для константы сообщения об ошибке

    class HstatHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            // Проверка прав администратора из модели UserLdap
            if (!\App\Models\UserLdap::checkIsAdmin($user->uid)) {
                $this->bot->send($user->uid, "⚠️ У вас нет прав на выполнение данной команды!");
                return;
            }

            // Собираем инфо о системе (аналог WebHookController->info)
            $info = [
                'php_version'                   => PHP_VERSION, 'laravel_version' => app()->version(),
                'memory_usage'                  => round(memory_get_usage() / 1024 / 1024, 2).' MB',
                'db_connection'                 => \Illuminate\Support\Facades\DB::connection('ssddb')
                    ->getDatabaseName(), 'time' => now()->toDateTimeString(),
            ];

            $this->bot->send($user->uid, "📊 <b>System Stats:</b>\n<pre>".json_encode($info, JSON_PRETTY_PRINT)."</pre>");
        }
    }
