<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Throwable;

    class ExecuteSwConf implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAlerts;

        // Время жизни задачи (должно быть меньше retry_after в config/queue.php)
        public $timeout = 1700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected string   $token,
            protected int      $messageId,
            protected string   $startMessage
        ) {}

        /**
         * @throws Throwable
         */
        public function handle(OtkApiService $otk, Transport $bot): void {
            $uid   = $this->user->uid;
            $token = $this->token;
            $elem  = explode('-', $this->switchName)[0];

            $baseHeader  = $this->startMessage."\n🚀 Заливка <b>$this->switchName</b>\nToken: <code>$token</code>\n\n";
            $state       = 1;
            $totalTime   = 0;
            $showKillBtn = true;
            $killBtn     = [[['text' => '🛑 Остановить заливку', 'callback_data' => "/killswc $token"]]];

            // Переменная для хранения последнего успешного лога
            $lastValidLog = "";
            $lastSentFullText = "";

            try {
                while ($state == 1) {
                    // Опрашиваем статус
                    $status = $otk->request("/switch/config/status/$token");

                    // 2. Обработка ошибки API (чтобы не затирать лог)
                    if (isset($status['result']) && $status['result'] === false) {
                        $errorMsg = $status['error']['msg'] ?? 'не найдено / таймаут';
                        if (!mb_stristr($status['output'], $errorMsg)) {
                            return;
                        }

                        $failText = $baseHeader;
                        if ($lastValidLog) {
                            $failText .= "<pre>" . htmlspecialchars(mb_substr($lastValidLog, -2500)) . "</pre>\n";
                        }
                        $failText .= "❌ <b>Ошибка API:</b> $errorMsg. Выход.";

                        $bot->update($uid, $this->messageId, $failText);
                        return;
                    }

                    // 3. Сбор лога из ответа
                    $output = $status['output'] ?? '';
                    $iterationLog = "";

                    foreach (explode("\n", $output) as $line) {
                        if (trim($line) !== "" && !preg_match('/(=|-{2,})/', $line)) {
                            $iterationLog .= trim($line) . "\n";
                        }
                    }

                    // 4. Если лог пришел — сохраняем его и обновляем сообщение
                    if ($iterationLog !== "") {
                        $lastValidLog = $iterationLog;

                        if (mb_stristr($lastValidLog, 'Отправляю файл конфигурации')) {
                            $showKillBtn = false;
                        }

                        $safeLog = htmlspecialchars($lastValidLog);
                        $displayText = $baseHeader . "<pre>" . mb_substr($safeLog, -3500) . "</pre>";

                        if (!$showKillBtn) {
                            $displayText .= "\n<i>Конфиг отправлен... отмена невозможна.</i>";
                        }

                        // 2. ПРОВЕРКА НА ИЗМЕНЕНИЕ ТЕКСТА
                        // Сравниваем текущий сформированный текст с тем, что отправляли в прошлый раз
                        if ($displayText !== $lastSentFullText) {
//                            Console::debug("DISPLAYTEXT CHANGED, SENDING UPDATE...");

                            $res = $bot->update($uid, $this->messageId, $displayText, $showKillBtn ? $killBtn : []);

                            // Запоминаем текст только если Telegram его принял (res != 0)
                            if ($res !== 0) {
                                $lastSentFullText = $displayText;
                            }
//                        } else {
//                            Console::debug("DISPLAYTEXT UNCHANGED, SKIP UPDATE.");
                        }
                    }

                    sleep(5);
                    $state = $status['state'] ?? 0;
                    $totalTime += 5;

                    // 5. Защита от вечного цикла
                    if ($totalTime >= 1600) {
                        $otk->request("/switch/config/kill/$token", [], true);

                        $timeoutText = $baseHeader;
                        if ($lastValidLog) {
                            $timeoutText .= "<pre>" . htmlspecialchars(mb_substr($lastValidLog, -2500)) . "</pre>\n";
                        }
                        $timeoutText .= "⌛️ Превышено время ожидания (1600с). Процесс убит.";

                        $bot->update($uid, $this->messageId, $timeoutText);
                        return;
                    }
                }
            } catch (Throwable $e) {
                Console::error("JOB FATAL ERROR: " . $e->getMessage());
                $bot->update($this->user->uid, $this->messageId, $baseHeader . "🚨 Ошибка воркера: " . $e->getMessage());
                throw $e;
            }

            // 6. Финальная обработка (в нее тоже можно передать $lastValidLog для Case 2)
            $this->processFinalState($bot, $otk, $state, $elem, $baseHeader, $status, $lastValidLog);
        }

        private function processFinalState($bot, $otk, $state, $elem, $baseHeader, $status, string $lastLog = ''): void {
            $uid = $this->user->uid;
            $finalText = $baseHeader;

            // Если есть накопленный лог, приклеиваем его (с экранированием)
            if ($lastLog !== '') {
                $finalText .= "<pre>" . htmlspecialchars(mb_substr($lastLog, -2500)) . "</pre>\n";
            }

            if ($state == 0) {
                // Успех: Обновляем сообщение, добавляя лог и кнопки действий
                $finalText .= "✅ <b>Коммутатор залит успешно!</b>";

                $bot->update($uid, $this->messageId, $finalText, [
                    [['text' => '🔍 Проверить элемент', 'callback_data' => "/elem " . current($elem)]],
                    [['text' => '📡 Пингануть', 'callback_даta' => "/ping $this->switchName"]]
                ]);

                $this->alert("✅ успешно залил $this->switchName", $this->user);

            } elseif ($state == 2) {
                // Ошибка заливки: сохраняем лог, чтобы видеть на чем упало
                $finalText .= "❌ <b>Ошибка! Коммутатор не залит.</b> Звони оператору.";

                $bot->update($uid, $this->messageId, $finalText);
                $this->alert("❌ НЕ залил $this->switchName", $this->user);

            } else {
                // Прочие ошибки (статус 3, 4 и т.д.)
                $msg = ($status['error']['msg'] ?? 'Неизвестная ошибка');
                $finalText .= "❌ <b>Сбой процесса:</b> $msg";

                $bot->update($uid, $this->messageId, $finalText);
                $this->alert("❌ Сбой заливки $this->switchName: $msg", $this->user);
            }
        }

    }
