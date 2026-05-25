<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class MagHandler extends BaseHandler {
        public function handle(UserLdap $user, array $params): void {
            // 1. Валидация параметров (switch, port, status)
            if (count($params) < 3) {
                $this->bot->send($user->uid, "⚠️ Использование: <code>/mag [коммутатор] [порт] [статус]</code>");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];
            $stat = $params[2];

            // 2. Определение действия (1 - вкл, 2 - выкл)
            [$status, $actionVerb] = match (strtolower((string)$stat)) {
                'вык', 'выкл', 'выключить', '-', '0' => [2, 'выключил'],
                'вк', 'вкл', 'включить', '+', '1'    => [1, 'включил'],
                default => [null, null],
            };

            if (!$status) {
                $this->bot->send($user->uid, "❌ Ошибка: Неверный статус порта.");
                return;
            }

            $this->startReply($user->uid, "🚀 Ой, чо щас будет....");

            $rebootDelayedSupports = false;
            $rebootDelayedEnabled = false;

            // 3. Страховка: Отложенный рестарт (только при выключении)
            if ($status === 2) {
                $this->appendReply($user->uid, "🛡 Попробую включить отложенный рестарт на <code>$swnm</code>...");

                $rebootRes = $this->otk->request(
                    "/switch/$swnm/reboot/delayed/enabled",
                    [
                        'enabled' => true,
                        'timeout' => 2,
                        'uname'   => $user->username
                    ],
                    true
                );

                if (!($rebootRes['result'] ?? false)) {
                    $errMsg = $rebootRes['error']['msg'] ?? 'неизвестно';
                    $this->appendReply($user->uid, str_contains($errMsg, 'Не поддерживается')
                        ? "⚠️ $swnm не умеет в отложенный рестарт, действую на свой страх и риск..."
                        : "⚠️ Ошибка страховки: $errMsg");
                } else {
                    $this->appendReply($user->uid, "✅ Отложенный рестарт (2 мин) включен.");
                    $rebootDelayedSupports = true;
                    $rebootDelayedEnabled = true;
                }
            }

            // 4. Основное действие: Переключение порта
            $res = $this->otk->request("/tg/$swnm/mag/$port/$status", [], true);

            $errorId = $res['error']['id'] ?? -1;
            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1  => 'Некорректное имя коммутатора',
                    2  => 'Коммутатор не найден',
                    3  => 'Коммутатор недоступен',
                    4  => 'Некорректный порт',
                    91 => 'Заданный порт НЕ магистральный! Ничего не трогаю.',
                    default => 'Ошибка API: '.($res['error']['msg'] ?? 'unknown'),
                };
                $this->appendReply($user->uid, "❌ $errorMsg");
                return;
            }

            // 5. Обработка последствий и проверка связи
            if ($res['result'] ?? false) {
                if ($status === 2) {
                    sleep(10);
                    $this->appendReply($user->uid, "📡 Ну $actionVerb я <code>$swnm / $port</code>. Проверяю связь...");

                    if ($this->pingHost($swnm)) {
                        $this->appendReply($user->uid, "🎉 Тебе повезло, <code>$swnm</code> не отвалился :)");
                        $this->finalizeSafeAction($user, $swnm, $rebootDelayedSupports, $rebootDelayedEnabled);
                    } else {
                        $this->appendReply($user->uid, "😱 Шеф, всё пропало! Ждем 20 сек, вдруг одумается...");
                        sleep(20);
                        if ($this->pingHost($swnm)) {
                            $this->appendReply($user->uid, "😅 Повезло, <code>$swnm</code> вернулся!");
                            $this->finalizeSafeAction($user, $swnm, $rebootDelayedSupports, $rebootDelayedEnabled);
                        } else {
                            $this->appendReply($user->uid, "💀 Всё плохо, <code>$swnm</code> недоступен...");
                        }
                    }
                } else {
                    $this->appendReply($user->uid, "✅ Порт <b>$port</b> на <code>$swnm</code> $actionVerb.");
                    if ($this->pingHost($swnm)) $this->appendReply($user->uid, "📡 Коммутатор доступен.");
                }

                // 6. Рассылка алертов дежурным
                $this->alert("$actionVerb порт <b>$swnm / $port</b>", $user);
            }
        }

        /**
         * Отключение страховки и сохранение конфига
         */
        private function finalizeSafeAction(UserLdap $user, string $swnm, bool $supports, bool $enabled): void
        {
            if ($supports && $enabled) {
                $this->appendReply($user->uid, "🧹 Выключаю отложенный рестарт...");
                $res = $this->otk->request("/switch/$swnm/reboot/delayed/enabled", [
                    'enabled' => false,
                    'uname'   => $user->username
                ], true);

                if ($res['result'] ?? false) {
                    $this->appendReply($user->uid, "💾 Сохраняю конфиг на <code>$swnm</code>...");
                    $this->otk->request("/switch/$swnm/config/save", ['uname' => $user->username], true);
                    $this->appendReply($user->uid, "✅ Конфиг сохранен.");
                }
            }
        }

        /**
         * Вспомогательный метод пинга (можно заменить на системный exec)
         */
        private function pingHost(string $host): bool {
            exec("ping -c 1 -W 2 ".escapeshellarg($host), $output, $result);
            return $result === 0;
        }
    }
