<?php

    namespace App\Telegram\Commands\Admin;

    use App\Http\Controllers\UserController;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;
    use Illuminate\Support\Facades\DB;

    // Для константы сообщения об ошибке

    class HstatHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            // Проверка прав администратора из модели UserLdap
            if (!$user->is_admin) {
                $this->bot->send($user->uid, "⚠️ У вас нет прав на выполнение данной команды!");
                return;
            }

            // Собираем инфо о системе (аналог WebHookController->info)
            $info = [
                'php_version'   => PHP_VERSION, 'laravel_version' => app()->version(),
                'memory_usage'  => round(memory_get_usage() / 1024 / 1024, 2).' MB',
                'db_connection' => DB::connection('ssddb')->getDatabaseName(),
                'time'          => now()->toDateTimeString(),
            ];

            $this->bot->send($user->uid, "📊 <b>System Stats:</b>\n<pre>".json_encode($info, JSON_PRETTY_PRINT)."</pre>");
        }
    }
