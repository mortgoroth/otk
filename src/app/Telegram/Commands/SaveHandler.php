<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\DebugController;

    class SaveHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];
            DebugController::write("swnm: $swnm", 'SAVE_COMMAND');

            // 2. Начало выполнения
            $this->startReply($user->uid, "💾 Сохраняю конфиг на <code>$swnm</code>...");

            // 3. Запрос к API (используем uname из LDAP для логов на стороне API)
            $res = $this->otk->request("/switch/$swnm/config/save", [
                'uname' => $user->username
            ], true); // Используем POST, так как это действие изменения

            // 4. Обработка результата
            $errorId = $res['error']['id'] ?? -1;

            if ($errorId === 0 && ($res['result'] ?? false)) {
                $msg = "✅ Конфиг на <b>$swnm</b> сохранен успешно.";
                $this->appendReply($user->uid, $msg);
                // $this->alert("сохранил конфиг на $swnm");
            } else {
                $errorMsg = $res['error']['msg'] ?? 'Ошибка сохранения';
                $this->appendReply($user->uid, "❌ Ошибка сохранения конфига <b>$swnm</b>: $errorMsg");
            }
            $this->logAction($user,'save', $params);
        }
    }
