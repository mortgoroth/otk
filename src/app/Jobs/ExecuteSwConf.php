<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;

    class ExecuteSwConf implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAlerts;

        // Время жизни задачи (должно быть меньше retry_after в config/queue.php)
        public $timeout = 1700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected string   $token,
            protected int      $messageId,
        ) {}

        public function handle(OtkApiService $otk, Transport $bot): void {
            $uid = $this->user->uid;
            $token = $this->token;
            $elem = explode('-', $this->switchName)[0];

            $baseHeader = "🚀 Заливка <b>$this->switchName</b>\nToken: <code>$token</code>\n\n";

            $state = 1;
            $totalTime = 0;
            $showKillBtn = true;
            $killBtn = [[['text' => '🛑 Остановить заливку', 'callback_data' => "/killswc $token"]]];

            while ($state == 1) {
                // Опрашиваем статус
                $status = $otk->request("/switch/config/status/$token");

                if (isset($status['result']) && $status['result'] === false) {
                    $bot->update($uid, $this->messageId, $baseHeader . "❌ Ошибка API: " . ($status['error']['msg'] ?? 'unknown'));
                    return;
                }

                $output = $status['output'] ?? '';
                $logText = "";

                // Формируем текст лога для текущего отображения
                foreach (explode("\n", $output) as $line) {
                    if (trim($line) !== "" && !preg_match('/(=|-{2,})/', $line)) {
                        $logText .= trim($line) . "\n";
                    }
                }

                if ($logText) {
                    // Если пошла запись — убираем кнопку отмены
                    if (mb_stristr($logText, 'Отправляю файл конфигурации')) {
                        $showKillBtn = false;
                    }

                    // Шлем последние 3500 символов, чтобы не превысить лимит Telegram
                    $displayText = $baseHeader . "<pre>" . mb_substr($logText, -3500) . "</pre>";
                    if (!$showKillBtn) $displayText .= "\n<i>Процесс записи... отмена невозможна.</i>";

                    $bot->update($uid, $this->messageId, $displayText, $showKillBtn ? $killBtn : []);
                }

                sleep(5);
                $state = $status['state'] ?? 0;
                $totalTime += 5;

                // Защита от вечного цикла (как в старом коде)
                if ($totalTime >= 1600) {
                    $otk->request("/switch/config/kill/$token", [], true);
                    $bot->update($uid, $this->messageId, $baseHeader . "⌛️ Превышено время ожидания (1600с). Процесс убит.");
                    return;
                }
            }

            // Финальная обработка состояний
            $this->processFinalState($bot, $otk, $state, $elem, $baseHeader, $status);
        }

        private function processFinalState($bot, $otk, $state, $elem, $baseHeader, $status): void {
            $uid = $this->user->uid;

            if ($state == 0) {
                // Успех: обновляем сообщение и добавляем кнопки действий
                $bot->update($uid, $this->messageId, $baseHeader . "✅ <b>Коммутатор залит успешно!</b>", [
                    [['text' => '🔍 Проверить элемент', 'callback_data' => "/elem $elem"]],
                    [['text' => '📡 Пингануть', 'callback_data' => "/ping $this->switchName"]]
                ]);
                $this->alert("✅ успешно залил $this->switchName", $this->user);
            } elseif ($state == 2) {
                $bot->update($uid, $this->messageId, $baseHeader . "❌ <b>Ошибка! Коммутатор не залит.</b> Звони оператору.");
                $this->alert("❌ НЕ залил $this->switchName", $this->user);
            } else {
                $msg = ($status['error']['msg'] ?? 'Неизвестная ошибка');
                $bot->update($uid, $this->messageId, $baseHeader . "❌ <b>Сбой:</b> $msg");
                $this->alert("❌ Сбой заливки $this->switchName: $msg", $this->user);
            }
        }
    }
