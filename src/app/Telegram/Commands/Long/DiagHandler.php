<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteDiag;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class DiagHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметров (Коммутатор и Порт)
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];

            // 2. Начало выполнения
            $this->startReply($user->uid, "🧪 Кабельная диагностика и ошибки <code>$swnm</code> / порт <code>$port</code>. Ожидание: 1 мин.");

            ExecuteDiag::dispatch($user, $swnm, $port, $this->messageId);
            $this->logAction($user,'diag', $params);
        }

    }
