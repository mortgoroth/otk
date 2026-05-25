<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteCab;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class CabHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка коммутатора
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1] ?? 'all';

            // 2. Анонс (без HTML, как в оригинале, или используем <code> для чистоты)
            $this->startReply($user->uid, "📏 Определение длины линии <code>$swnm</code> / порт: <code>$port</code>...");

            ExecuteCab::dispatch($user, $swnm, $port, $this->messageId);
            $this->logAction($user,'broken', $params);
        }

    }
