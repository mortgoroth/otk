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

            $this->startReply($user->uid, "🚀 Процесс заливки <code>$swnm</code> запущен...");

            ExecuteSwConf::dispatch($user, $swnm, $this->messageId);

            $this->logAction($user,'config', $params);

        }

    }
