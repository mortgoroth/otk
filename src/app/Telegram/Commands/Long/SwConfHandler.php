<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteSwConf;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Telegram\Commands\BaseHandler;
    use Exception;

    class SwConfHandler extends BaseHandler {

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

            // Регистрация замены в БД (старая логика)
            try {
                $this->otk->request("/$swnm/replace2db", [], true);
            } catch (Exception $e) {
                Console::error("Error while replace2db: ".$e->getMessage());
            }

            // 3. Создаем "холст" для процесса
            $this->startReply($user->uid, "🚀 Процесс заливки <code>$swnm</code> запущен...");

            // 4. Запрос токена в API (быстрый запрос)
            $begin = $this->otk->request('/switch/config/start', [
                'host'  => $swnm,
                'uname' => $user->username,
                'from'  => 'bot'
            ], true);

            if (($begin['error']['id'] ?? -1) !== 0) {
                $this->appendReply($user->uid, "❌ Ошибка запуска: " . ($begin['error']['msg'] ?? 'API error'));
                return;
            }

            $token = $begin['token'] ?? null;
            if (!$token) {
                $this->appendReply($user->uid, "❌ Ошибка: API не вернуло токен.");
                return;
            }

            // Дописываем токен в то же сообщение
            $this->appendReply($user->uid, "✅ Сессия создана. Token: <code>$token</code>");

            // 5. Передаем управление в очередь
            ExecuteSwConf::dispatch($user, $swnm, $token, $this->messageId, $this->accumulatedText);

            // 6. Логгирование и алерт
            $this->logAction($user, 'config', $params);
            $this->alert("начал заливку $swnm", $user);
        }
    }
