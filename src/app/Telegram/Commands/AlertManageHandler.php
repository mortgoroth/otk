<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class AlertManageHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка прав (только админы могут управлять алертами других)
            if (!$user->is_admin) {
                $this->bot->send($user->uid, "⚠️ У вас нет прав на управление уведомлениями.");
                return;
            }

            // 2. Проверка параметров
            $targetUsername = $params[0] ?? null;
            if (!$targetUsername) {
                $this->bot->send($user->uid, "⚠️ Использование: <code>/alert_on логин</code> или <code>/alert_off логин</code>");
                return;
            }

            // 3. Определяем действие
            $cmdName = request()->get('command_name');
            $enable = str_contains($cmdName, 'on');
            $statusText = $enable ? 'включены' : 'выключены';

            // 4. Поиск целевого пользователя в нашей БД
            $targetUser = UserLdap::where('username', $targetUsername)
                ->first();

            if (!$targetUser) {
                $this->bot->send($user->uid, "❌ Пользователь <code>$targetUsername</code> не найден в базе бота.");
                return;
            }

            // 5. Обновление статуса
            $targetUser->update(['alert' => $enable]);

            // 6. Ответ админу
            $msg = "🔔 Уведомления для <b>{$targetUser->ldap_full_name}</b> (@$targetUsername) $statusText.";
            $this->bot->send($user->uid, $msg);

            // 7. Опционально: Уведомляем самого пользователя
            try {
                $userMsg = $enable ? "🚀 Вам включены системные уведомления о действиях инженеров." : "🔕 Системные уведомления для вас отключены.";
                $this->bot->send($targetUser->uid, $userMsg);
            } catch (\Exception $e) {
                // Пользователь мог заблокировать бота
            }
        }
    }
