<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\DebugController;
    use Exception;

    class SwConfHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Валидация входных данных
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];
            if (!preg_match('/(a4-)?\d{5}-\d{1,3}/i', $swnm)) {
                $this->bot->send($user->uid, "❌ Некорректное имя коммутатора.");
                return;
            }

            // Регистрация замены в БД (старая логика)
            try {
                $this->otk->request("/$swnm/replace2db", [], true);
            } catch (Exception $e) {
                DebugController::write($e->getMessage(), "Error while replace2db");
            }

            $elem = explode('-', $swnm)[0];
            $this->startReply($user->uid, "🚀 Процесс заливки <code>$swnm</code> запущен...");

            // 2. Старт процесса
            $begin = $this->otk->request('/switch/config/start', [
                'host' => $swnm, 'uname' => $user->username, 'from' => 'bot'
            ], true);

            DebugController::write($begin, 'CONFIG_START');
            sleep(5);

            if (($begin['error']['id'] ?? -1) !== 0) {
                $this->appendReply($user->uid, "❌ ".($begin['error']['msg'] ?? 'Ошибка запуска'));
                return;
            }

            $token = $begin['token'];

            // Кнопка отмены
            $killBtn = [[['text' => '🛑 Остановить заливку', 'callback_data' => "/killswc $token"]]];
            $this->bot->update($user->uid, $this->messageId, $this->accumulatedText."\nToken: <code>$token</code>", $killBtn);

            // 3. Цикл опроса статуса
            $state = 1;
            $totalTime = 0;
            $showKillBtn = true;

            while ($state == 1) {
                $status = $this->otk->request("/switch/config/status/$token");

                if (isset($status['result']) && !$status['result']) {
                    $this->appendReply($user->uid, "❌ ".($status['error']['msg'] ?? 'Ошибка статуса'));
                    return;
                }

                $output = $status['output'] ?? '';
                $reply = "";

                foreach (explode("\n", $output) as $line) {
                    if (trim($line) !== "" && !preg_match('/(=|-{2,})/', $line)) {
                        $reply .= trim($line)."\n";
                    }
                }

                if ($reply) {
                    if (mb_stristr($reply, 'Отправляю файл конфигурации')) {
                        $showKillBtn = false; // После отправки конфига убивать поздно
                        $reply .= "\n<i>Процесс необратим, убираю кнопку...</i>\n";
                    }

                    $text = "🚀 Процесс заливки <code>$swnm</code> запущен\nToken: <code>$token</code>\n\n$reply";
                    $this->bot->update($user->uid, $this->messageId, $text, $showKillBtn ? $killBtn : []);
                }

                sleep(5);
                $state = $status['state'] ?? 0;
                $totalTime += 5;

                if ($totalTime >= 1600) {
                    $this->killProcess($token, "⌛️ Timeout. Процесс убит по времени.");
                    return;
                }
            }

            // 4. Финал процесса
            switch ($state) {
                case 0:
                    $this->bot->sendInline($user->uid, "✅ Коммутатор <b>$swnm</b> залит успешно.", [
                        [['text' => '🔍 Проверить элемент', 'callback_data' => "/elem $elem"]],
                        [['text' => '📡 Пингануть', 'callback_data' => "/ping $swnm"]]
                    ]);
                    break;
                case 2:
                    $this->killProcess($token, "❌ Коммутатор $swnm не залит! Ошибка! Звони оператору.");
                    break;
                default:
                    $msg = ($status['error']['msg'] ?? 'Ошибка')."! Пробуй еще раз или звони оператору.";
                    $this->killProcess($token, $msg);
            }
        }

        private function killProcess (string $token, string $msg):void {
            $this->otk->request("/switch/config/kill/$token", [], true);
            $this->appendReply(request('uid'), $msg);
        }
    }
