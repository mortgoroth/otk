<?php

    namespace App\Telegram\Commands\Long;

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

            $report = "🔍 Проверяю коммутатор <code>$swnm</code>...";
            // 3. Проверка типа коммутатора (поддерживает ли он blink)
            $check = $this->otk->request("/switch/$swnm/blink/check");

            if (($check['error']['id'] ?? -1) === 0 && ($check['result'] ?? false)) {
                $report .= "\n💡 Всё ок! Мигаю индикаторами...";
                $this->appendReply($user->uid, $report);

                // 4. Выполнение команды (POST-запрос)
                $this->otk->request("/switch/$swnm/blink", [], true);

                $report .= "\n✅ Готово!";
                $this->appendReply($user->uid, $report);
            } else {
                // Если API вернул ошибку или неподдерживаемый тип
                $errorMsg = $check['error']['msg'] ?? 'Неподдерживаемый тип коммутатора.';
                $this->appendReply($user->uid, "report\n\n❌ $errorMsg");
            }

            // 5. Логируем действие
            $this->logAction($user, 'blink', $params);
        }
    }
