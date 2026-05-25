<?php

    namespace App\Console\Commands;

    use App\Models\UserLdap;
    use Illuminate\Console\Command;
    use App\Services\Telegram\BotEngine;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\DB;
    use Exception;

    class TelegramBotRun extends Command {
        protected $signature = 'bot:run';
        protected $description = 'Запуск Telegram бота в режиме Long Polling';

        public function handle (BotEngine $engine) {
            $offset = 0;
            $this->info("[".date('Y-m-d H:i:s')."] Бот запущен...");
            $conf = conf('telegram');
            $nextCheck = time() + 3600;

            while (true) {
                if (time() >= $nextCheck) {
                    $this->logoutExpiredUsers($engine);
                    $nextCheck = time() + 3600; // Ставим метку на следующий час
                }
                try {
                    DB::connection('ssddb')
                        ->getPdo();

                    $response = Http::timeout(35)
                        ->get("{$conf['api_url']}/bot{$conf['otk_service_bot']['token']}/getUpdates", [
                            'offset' => $offset,
                            'timeout' => 30
                        ]);

                    if ($response->successful()) {
                        foreach ($response->json('result') as $update) {
                            $offset = $update['update_id'] + 1;

                            try {
                                $this->info("[".date('Y-m-d H:i:s')."]".json_encode($update, JSON_UNESCAPED_UNICODE));
                                $engine->handle($update);
                            } catch (Exception $e) {
                                $this->error("Ошибка обработки Update ID {$update['update_id']}: ".$e->getMessage());
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->error("[".date('Y-m-d H:i:s')."] Ошибка связи или БД: ".$e->getMessage());
                    sleep(2);
                }

                // Контроль памяти (100МБ)
                if (memory_get_usage() > 100 * 1024 * 1024) {
                    $this->warn("Перезапуск по памяти...");
                    return 0;
                }
            }
        }

        private function logoutExpiredUsers(BotEngine $engine): void {
            $dayAgo = time() - 86400;

            // Выбираем только тех, кто в системе и чей logon устарел
            $expiredUsers = UserLdap::where('authorized', true)
                ->where('last_logon', '<', $dayAgo)
                ->get();

            if ($expiredUsers->isEmpty()) {
                return;
            }

            foreach ($expiredUsers as $user) {
                $user->update([
                    'authorized' => false,
                    'attempt'    => false // Сбрасываем флаги попыток
                ]);

                try {
                    $engine->getBot()->send(
                        $user->uid,
                        "🛑 <b>Сессия истекла</b>\nПрошло более 24 часов с момента входа. Авторизуйтесь снова.",
                        [['login']]
                    );
                    $this->info("[".date('Y-m-d H:i:s')."] Авто-разлогин юзера: $user->uid");
                } catch (Exception $e) {
                    $this->error("Ошибка уведомления {$user->uid}: " . $e->getMessage());
                }
            }

            $this->info("[".date('Y-m-d H:i:s')."] Очистка завершена. Удалено сессий: " . $expiredUsers->count());
        }

    }
