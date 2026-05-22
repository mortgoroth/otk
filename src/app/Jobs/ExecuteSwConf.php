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

    class ExecuteSwConf implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
        use HasAlerts;


        public $timeout = 700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected int      $messageId,
        ) {}

        public function handle (OtkApiService $otk, Transport $bot):void {
            // 2. Начало выполнения
            $uid = $this->user->uid;

            $killProcess  = function (string $token, string $msg) use (&$otk, &$bot) {
                $otk->request("/switch/config/kill/$token", [], true);
                $bot->update(request('uid'), $this->messageId, $msg);
            };

            $reply = "🚀 Процесс заливки <code>$this->switchName</code> запущен...\n";
            $elem = explode('-', $this->switchName)[0];

            // 2. Старт процесса
            $begin = $otk->request(
                '/switch/config/start',
                [
                    'host' => $this->switchName,
                    'uname' => $this->user->username,
                    'from' => 'bot'
                ],
                true
            );

            Console::debug("CONFIG_START: ".json_encode($begin, JSON_UNESCAPED_UNICODE));
            sleep(5);

            if (($begin['error']['id'] ?? -1) !== 0) {
                $reply .= "❌ ".($begin['error']['msg'] ?? 'Ошибка запуска');
                $bot->update($uid, $this->messageId, $reply);
                return;
            }
            $this->alert("начал заливку $this->switchName", $this->user);
            $token = $begin['token'];

            // Кнопка отмены
            $killBtn = [[['text' => '🛑 Остановить заливку', 'callback_data' => "/killswc $token"]]];
            $reply .= "\nToken: <code>$token</code>";
            $bot->update($uid, $this->messageId, $reply, $killBtn);

            // 3. Цикл опроса статуса
            $state = 1;
            $totalTime = 0;
            $showKillBtn = true;

            while ($state == 1) {
                $status = $otk->request("/switch/config/status/$token");

                if (isset($status['result']) && !$status['result']) {
                    $reply .= "\n❌ ".($status['error']['msg'] ?? 'Ошибка статуса');
                    $bot->update($uid, $this->messageId, $reply);
                    return;
                }

                $output = $status['output'] ?? '';
//                $reply = "";

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

                    $text = "🚀 Процесс заливки <code>$this->switchName</code> запущен\nToken: <code>$token</code>\n\n$reply";
                    $bot->update($uid, $this->messageId, $text, $showKillBtn ? $killBtn : []);
                }

                sleep(5);
                $state = $status['state'] ?? 0;
                $totalTime += 5;

                if ($totalTime >= 1600) {
                    $killProcess($token, "⌛️ Timeout. Процесс убит по времени.");
                    return;
                }
            }

            // 4. Финал процесса
            switch ($state) {
                case 0:
                    $bot->sendInline(
                        $uid,
                        "✅ Коммутатор <b>$this->switchName</b> залит успешно.",
                        [
                            [['text' => '🔍 Проверить элемент', 'callback_data' => "/elem $elem"]],
                            [['text' => '📡 Пингануть', 'callback_data' => "/ping $this->switchName"]]
                        ]
                    );
                    $this->alert("✅ успешно залил $this->switchName", $this->user);

                    break;
                case 2:
                    $killProcess($token, "❌ Коммутатор $this->switchName не залит! Ошибка! Звони оператору.");
                    $this->alert("❌ НЕ залил $this->switchName", $this->user);

                    break;
                default:
                    $this->alert("❌ НЕ залил $this->switchName", $this->user);
                    $msg = ($status['error']['msg'] ?? 'Ошибка')."! Пробуй еще раз или звони оператору.";
                    $killProcess($token, $msg);
            }
        }

    }
