<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;

    class BlinkHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра (используем первый элемент массива)
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];

            // 2. Начало выполнения
            $this->startReply($user->uid, "🔍 Проверяю коммутатор <code>$swnm</code>...");

            // 3. Проверка типа коммутатора (поддерживает ли он blink)
            $check = $this->otk->request("/switch/$swnm/blink/check");

            if (($check['error']['id'] ?? -1) === 0 && ($check['result'] ?? false)) {
                $this->appendReply($user->uid, "💡 Всё ок! Мигаю индикаторами...");

                // 4. Выполнение команды (POST-запрос)
                $this->otk->request("/switch/$swnm/blink", [], true);

                $this->appendReply($user->uid, "✅ Готово!");
            } else {
                // Если API вернул ошибку или неподдерживаемый тип
                $errorMsg = $check['error']['msg'] ?? 'Неподдерживаемый тип коммутатора.';
                $this->appendReply($user->uid, "❌ $errorMsg");
            }

            // 5. Логируем действие
            $this->logAction($user, 'blink', $params);
        }
    }
