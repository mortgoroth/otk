<?php

    namespace App\Console\Commands;

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

            while (true) {
                try {
                    // Проверка соединения с БД (фишка из старого кода)
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
    }
