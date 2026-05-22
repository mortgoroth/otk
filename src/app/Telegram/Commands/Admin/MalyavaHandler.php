<?php

    namespace App\Telegram\Commands\Admin;

    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;
    use Exception;

    class MalyavaHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверяем наличие адресата
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ Кому слать-то?");
                return;
            }

            // 2. Проверяем наличие текста
            if (!isset($params[1])) {
                $this->bot->send($user->uid, "⚠️ А чо шлём?");
                return;
            }

            $targetLogin = $params[0];

            // 3. Защита от самоспама (из старого select)
            if ($targetLogin === $user->username) {
                $this->bot->send($user->uid, "🤡 Дурак чо ль самому себе малявы слать?");
                return;
            }

            // 4. Формируем текст сообщения (все параметры после первого)
            $messageText = implode(' ', array_slice($params, 1));

            // 5. Ищем Telegram UID получателя через API
            $response = $this->otk->request("/tg/users/get/{$targetLogin}");

            // В старом коде ты проверял $to['result']
            if (empty($response) || !isset($response['uid'])) {
                $this->bot->send($user->uid, "📵 Абонент <b>$targetLogin</b> не отвечает или не существует )))");
                return;
            }

            $targetUid = (int) $response['uid'];

            try {
                // 6. Шлем маляву адресату
                // Можно добавить префикс: "Псст... Тебе {$user->username} маляву шлет: "
                $this->bot->send($targetUid, "✉️ <b>Малява от {$user->username}:</b>\n\n$messageText");

                // 7. Рапортуем отправителю
                $this->bot->send($user->uid, "✅ Малява улетела к <b>$targetLogin</b>");

            } catch (Exception $e) {
                $this->bot->send($user->uid, "❌ Не удалось доставить: ".$e->getMessage());
            }
        }
    }
