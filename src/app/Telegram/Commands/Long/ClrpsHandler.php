<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteClrps;
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
            ExecuteClrps::dispatch($user, $swnm, $range, $this->messageId, $this->accumulatedText);

            $this->appendReply($user->uid, "\n🏁 Обработка завершена.");
            $this->logAction($user, 'clrps', $params);
        }
    }
