<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteBlink;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class BlinkHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра (используем первый элемент массива)
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];

            // 2. Начало выполнения
            $this->startReply($user->uid, "🔍 Проверяю коммутатор <code>$swnm</code>...");

            ExecuteBlink::dispatch($user, $swnm, $this->messageId);

            // 5. Логируем действие
            $this->logAction($user, 'blink', $params);
        }
    }
