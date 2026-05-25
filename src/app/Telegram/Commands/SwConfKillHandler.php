<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class SwConfKillHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверяем наличие токена (аналог checkParam)
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Токен не задан");
                return;
            }

            $token = $params[0];

            // 2. Начало выполнения
            $this->startReply($user->uid, "🔪 Убиваю процесс заливки коммутатора <code>$token</code>...");

            // 3. Запрос к API (POST запрос)
            $res = $this->otk->request("/switch/config/kill/$token", [], true);

            // Небольшая пауза как в оригинале
            sleep(2);

            // 4. Обработка ошибок через match (PHP 8.2+)
            $errorId = $res['error']['id'] ?? -1;

            $message = match ($errorId) {
                0 => "✅ swconfig $token process killing ".(($res['status'] ?? '') === 'killed' ? 'done' : 'error'),
                12 => "⚠️ Токен $token не найден",
                13 => "⚠️ А это точно заливка?",
                14 => "⚠️ pid не найден",
                15 => "⚠️ Процесс с ".($res['shPID'] ?? 'unknown')." не найден",
                30 => "✅ {$res['error']['msg']}",
                default => "❌ Неизвестная ошибка API: ".($res['error']['msg'] ?? 'no details'),
            };

            // 5. Обновляем исходное сообщение результатом
            $this->appendReply($user->uid, $message);
            $this->logAction($user,'killswc', $params);
        }
    }
