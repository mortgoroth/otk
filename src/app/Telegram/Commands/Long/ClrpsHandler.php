<?php

    namespace App\Telegram\Commands\Long;

    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class ClrpsHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка коммутатора
            if (empty($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            if (empty($params[2])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }

            $swnm = $params[0];
            $portsRaw = $params[1] ?? null;

            // 2. Парсинг диапазона портов (1-5 или просто 5)
            $range = [];
            if ($portsRaw) {
                $portsRaw = str_replace(' ', '', $portsRaw);
                $portRange = explode('-', $portsRaw);
                $range = (count($portRange) > 1) ? range((int) $portRange[0], (int) $portRange[1]) : [(int) $portRange[0]];
            } else {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }

            // 3. Начало выполнения
            $this->startReply($user->uid, "🧹 Чистка <b>port-security</b> на <code>$swnm</code>...");
            foreach ($this->range as $port) {
                $this->appendReply($user->uid, "🔍 Чищу порт <code>$port</code>...");

                // POST запрос к API
                $res = $this->otk->request("/tg/$this->switchName/clrps/$port", [], true);

                $errorId = $res['error']['id'] ?? -1;

                $status = match ($errorId) {
                    0  => "✅ <code>$this->switchName/$port</code>: Ok",
                    3  => "⚠️ Коммутатор недоступен",
                    50 => "❌ Ошибка. Port-security порта $port не очищен",
                    1  => "❌ Некорректное имя коммутатора",
                    2  => "❌ Коммутатор не найден",
                    4  => "❌ Некорректный порт",
                    default => "❌ Ошибка API (ID: $errorId)"
                };

                $this->appendReply($user->uid,  " — $status");

                // Если фатальная ошибка коммутатора — прекращаем цикл
                if (in_array($errorId, [1, 2, 3])) {
                    break;
                }
            }


            $this->appendReply($user->uid, "\n🏁 Обработка завершена.");
            $this->logAction($user, 'clrps', $params);
        }
    }
