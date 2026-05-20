<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class MagHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Валидация параметров
            if (!isset($params[0]) || !isset($params[1]) || !isset($params[2])) {
                $this->bot->send($user->uid, "⚠️ Ой, чо щас будет.... А, не, норм. Не все параметры заданы.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];
            $stat = $params[2];

            // Определение статуса (1 - вкл, 2 - выкл)
            [$status, $actionVerb] = match (strtolower((string) $stat)) {
                'вык', 'выкл', 'выключить', '-', '0' => [2, 'выключил'],
                'вк', 'вкл', 'включить', '+', '1' => [1, 'включил'],
                default => [null, null],
            };

            if (!$status) {
                $this->bot->send($user->uid, "❌ Ошибка ввода данных (статус).");
                return;
            }

            $this->startReply($user->uid, "⚙️ Работаю с магистралью <code>$swnm</code> / порт <code>$port</code>...");

            $rebootDelayedSupports = false;
            $rebootDelayedEnabled = false;

            // 2. Страховка: Отложенный рестарт при выключении порта
            if ($status == 2) {
                $this->appendReply($user->uid, "🛡 Попробую включить отложенный рестарт...");
                $rebootDelayed = $this->otk->request("/switch/$swnm/reboot/delayed/enabled", [
                    'enabled' => true, 'timeout' => 2, 'uname' => $user->username
                ], true);

                if (!($rebootDelayed['result'] ?? false)) {
                    $errMsg = $rebootDelayed['error']['msg'] ?? '';
                    if (str_contains($errMsg, 'Не поддерживается')) {
                        $this->appendReply($user->uid, "⚠️ <code>$swnm</code> не умеет в отложенный рестарт, увы...");
                    } else {
                        $this->appendReply($user->uid, "⚠️ Ошибка страховки: $errMsg");
                    }
                } else {
                    $this->appendReply($user->uid, "✅ Отложенный рестарт включен (2 мин).");
                    $rebootDelayedSupports = true;
                    $rebootDelayedEnabled = true;
                }
            }

            // 3. Основное действие (переключение порта)
            $res = $this->otk->request("/tg/$swnm/mag/$port/$status", [], true);

            $errorId = $res['error']['id'] ?? -1;
            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => 'Некорректное имя коммутатора',
                    2 => 'Коммутатор не найден',
                    3 => 'Коммутатор недоступен',
                    4 => 'Порт не задан или неправильный',
                    91 => 'Заданный порт НЕ магистральный! Ничего не трогаю.',
                    default => 'Ошибка API: '.($res['error']['msg'] ?? 'unknown'),
                };
                $this->appendReply($user->uid, "❌ $errorMsg");
                return;
            }

            // 4. Проверка последствий
            if ($status == 2) {
                sleep(10);
                $this->appendReply($user->uid, "📡 Ну $actionVerb я <code>$swnm / $port</code>. Проверяю связь...");

                if (ping($swnm)) {
                    $this->appendReply($user->uid, "🎉 Тебе повезло, <code>$swnm</code> не отвалился :)");
                    $this->disableRebootAndSave($user, $swnm, $rebootDelayedSupports, $rebootDelayedEnabled);
                } else {
                    $this->appendReply($user->uid, "😱 Шеф, всё пропало! Ждем 20 сек, вдруг одумается...");
                    sleep(20);
                    if (ping($swnm)) {
                        $this->appendReply($user->uid, "😅 Повезло, <code>$swnm</code> вернулся!");
                        $this->disableRebootAndSave($user, $swnm, $rebootDelayedSupports, $rebootDelayedEnabled);
                    } else {
                        $this->appendReply($user->uid, "💀 Всё плохо, <code>$swnm</code> недоступен...");
                    }
                }
            } else {
                $this->appendReply($user->uid, "✅ Порт <b>$port</b> на <code>$swnm</code> $actionVerb.");
                if (ping($swnm))
                    $this->appendReply($user->uid, "📡 Коммутатор доступен.");
            }

            // Логирование и алерты (как в оригинале)
            $this->logAction($user, 'mag', $params, $this->accumulatedText);
//             $this->alert(...); // Если есть сервис алертов
        }

        /**
         * Снятие страховки и сохранение конфига
         */
        private function disableRebootAndSave (UserLdap $user, string $swnm, bool $supports, bool $enabled):void {
            if ($supports && $enabled) {
                $this->appendReply($user->uid, "🧹 Выключаю отложенный рестарт...");
                $res = $this->otk->request("/switch/$swnm/reboot/delayed/enabled", [
                    'enabled' => false, 'uname' => $user->username
                ], true);

                if ($res['result'] ?? false) {
                    $this->appendReply($user->uid, "💾 Сохраняю конфиг на <code>$swnm</code>...");
                    $this->otk->request("/switch/$swnm/config/save", ['uname' => $user->username], true);
                    $this->appendReply($user->uid, "✅ Конфиг сохранен!");
                } else {
                    $this->appendReply($user->uid, "❓ Отложенный рестарт всё ещё включен... Проверьте вручную!");
                }
            }
        }
    }
