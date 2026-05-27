<?php

    namespace App\Telegram\Commands\Long;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Telegram\Commands\BaseHandler;
    use Exception;
    use Illuminate\Support\Facades\Cache;
    use Throwable;

    class SwConfHandler extends BaseHandler {

        /**
         * @throws Throwable
         */
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
            try {
                $this->otk->request("/$swnm/replace2db", [], true);
            } catch (Exception $e) {
                Console::error("Error while replace2db: ".$e->getMessage());
            }
            $this->startReply($user->uid, "🚀 Процесс заливки <code>$swnm</code> запущен...");
            $begin = $this->otk->request('/switch/config/start', [
                'host'  => $swnm,
                'uname' => $user->username,
                'from'  => 'bot'
            ], true);
            if (($begin['error']['id'] ?? -1) !== 0) {
                $this->appendReply($user->uid, "❌ Ошибка запуска: ".($begin['error']['msg'] ?? 'API error'));
                return;
            }
            $token = $begin['token'] ?? null;
            if (!$token) {
                $this->appendReply($user->uid, "❌ Ошибка: API не вернуло токен.");
                return;
            }
            $this->appendReply($user->uid, "✅ Сессия создана. Token: <code>$token</code>");
            $baseHeader  = $this->accumulatedText."\n🚀 Заливка <b>$swnm</b>\nToken: <code>$token</code>\n\n";
            $state       = 1;
            $totalTime   = 0;
            $showKillBtn = true;
            $killBtn     = [[['text' => '🛑 Остановить заливку', 'callback_data' => "/killswc $token"]]];
            $lastValidLog = "";
            $lastSentFullText = "";
            try {
                while ($state == 1) {
                    if (Cache::has("kill_signal_$token")) {
                        $this->stopAndExit($this->bot, $token, $user->uid, $baseHeader, $lastValidLog);
                        return; // Мгновенный выход
                    }
                    $status = $this->otk->request("/switch/config/status/$token");
                    if (isset($status['result']) && $status['result'] === false) {
                        if (Cache::has("kill_signal_$token")) {
                            $this->stopAndExit($this->bot, $token, $user->uid, $baseHeader, $lastValidLog);
                            return; // Мгновенный выход
                        }
                        $errorMsg = $status['error']['msg'] ?? 'не найдено / таймаут';
                        if (!mb_stristr($status['output'], $errorMsg)) {
                            return;
                        }
                        $failText = $baseHeader;
                        if ($lastValidLog) {
                            $failText .= "<pre>".htmlspecialchars(mb_substr($lastValidLog, -2500))."</pre>\n";
                        }
                        $failText .= "❌ <b>Ошибка API:</b> $errorMsg. Выход.";

                        $this->appendReply($user->uid, $failText);
                        return;
                    }
                    $output = $status['output'] ?? '';
                    $iterationLog = "";
                    foreach (explode("\n", $output) as $line) {
                        if (trim($line) !== "" && !preg_match('/(=|-{2,})/', $line)) {
                            $iterationLog .= trim($line)."\n";
                        }
                    }
                    if ($iterationLog !== "") {
                        $lastValidLog = $iterationLog;
                        if (mb_stristr($lastValidLog, 'Отправляю файл конфигурации')) {
                            $showKillBtn = false;
                        }
                        $safeLog = htmlspecialchars($lastValidLog);
                        $displayText = $baseHeader."<pre>".mb_substr($safeLog, -3500)."</pre>";
                        if (!$showKillBtn) {
                            $displayText .= "\n<i>Конфиг отправлен... отмена невозможна.</i>";
                        }
                        if ($displayText !== $lastSentFullText) {
                            $res = $this->bot->update($user->uid, $this->messageId, $displayText, $showKillBtn ? $killBtn : []);

                            if ($res !== 0) {
                                $lastSentFullText = $displayText;
                            }
                        }
                    }
                    sleep(5);
                    $state = $status['state'] ?? 0;
                    $totalTime += 5;
                    if ($totalTime >= 1600) {
                        $this->otk->request("/switch/config/kill/$token", [], true);

                        $timeoutText = $baseHeader;
                        if ($lastValidLog) {
                            $timeoutText .= "<pre>".htmlspecialchars(mb_substr($lastValidLog, -2500))."</pre>\n";
                        }
                        $timeoutText .= "⌛️ Превышено время ожидания (1600с). Процесс убит.";

                        $this->appendReply($user->uid, $timeoutText);
                        return;
                    }
                }
            } catch (Throwable $e) {
                Console::error("JOB FATAL ERROR: ".$e->getMessage());
                $this->appendReply($user->uid, $baseHeader."🚨 Ошибка воркера: ".$e->getMessage());
                throw $e;
            }
            $this->processFinalState($this->bot, $user, $state, $swnm, $baseHeader, $status, $lastValidLog);
            $this->logAction($user, 'config', $params);
            $this->alert("начал заливку $swnm", $user);
        }

        private function processFinalState($bot, $user, $state, $swnm, $baseHeader, $status, string $lastLog = ''): void {
            $elem  = explode('-', $swnm)[0];
            $uid = $user->uid;
            $finalText = $baseHeader;
            if ($lastLog !== '') {
                $finalText .= "<pre>".htmlspecialchars(mb_substr($lastLog, -2500)) . "</pre>\n";
            }
            if ($state == 0) {
                $finalText .= "✅ <b>Коммутатор залит успешно!</b>";
                $bot->update($uid, $this->messageId, $finalText, [
                    [['text' => '🔍 Проверить элемент', 'callback_data' => "/elem " . $elem]],
                    [['text' => '📡 Пингануть', 'callback_data' => "/ping $swnm"]]
                ]);
                $this->alert("✅ успешно залил $swnm", $user);
            } elseif ($state == 2) {
                $finalText .= "❌ <b>Ошибка! Коммутатор не залит.</b> Звони оператору.";
                $bot->update($uid, $this->messageId, $finalText);
                $this->alert("❌ НЕ залил $swnm", $user);

            } else {
                $msg = ($status['error']['msg'] ?? 'Неизвестная ошибка');
                $finalText .= "❌ <b>Сбой процесса:</b> $msg";
                $bot->update($uid, $this->messageId, $finalText);
                $this->alert("❌ Сбой заливки $swnm: $msg", $user);
            }
        }

        private function stopAndExit($bot, $token, int $uid, string $baseHeader, string $lastLog): void {
            $stopText = $baseHeader;
            if ($lastLog !== '') {
                $stopText .= "<pre>" . htmlspecialchars(mb_substr($lastLog, -2500)) . "</pre>\n";
            }
            $stopText .= "\n🛑 <b>Остановка...</b>";
            Cache::forget("kill_signal_$token"); // Подчищаем за собой
            $bot->update($uid, $this->messageId, $stopText, []);
        }

    }
