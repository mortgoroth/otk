<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use Exception;
    use Illuminate\Container\EntryNotFoundException;
    use Illuminate\Contracts\Container\CircularDependencyException;
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\NotFoundExceptionInterface;

    class AdminManageHandler extends BaseHandler {

        /**
         * @throws CircularDependencyException
         * @throws EntryNotFoundException
         * @throws NotFoundExceptionInterface
         * @throws ContainerExceptionInterface
         */
        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка прав (только админы могут управлять назначением других админов)
            if (!$user->is_admin) {
                $this->bot->send($user->uid, "⚠️ У вас нет прав на управление пользователями.");
                return;
            }

            // 2. Проверка параметров
            $targetUsername = $params[0] ?? null;
            if (!$targetUsername) {
                $this->bot->send($user->uid, "⚠️ Использование: <code>/admin_on логин</code> или <code>/admin_off логин</code>");
                return;
            }

            // 3. Определяем действие
            $cmdName = request()->get('command_name');
            $enable = str_contains($cmdName, 'on');
            $statusText = $enable ? 'добавлен в администраторы' : 'исключен из администраторов';

            // 4. Поиск целевого пользователя в нашей БД
            $targetUser = UserLdap::whereUsername($targetUsername)
                ->first();

            if (!$targetUser) {
                $this->bot->send($user->uid, "❌ Пользователь <code>$targetUsername</code> не найден в базе бота.");
                return;
            }

            // 5. Обновление статуса
            $targetUser->update(['is_admin' => $enable]);

            // 6. Ответ админу
            $adminIcon = $enable ? "👨‍💻" : "🚫";
            $statusText = $enable ? 'назначен <b>Администратором</b>' : 'исключен из <b>Администраторов</b>';

            $msg = "$adminIcon Пользователь <b>$targetUser->ldap_full_name</b> (@$targetUsername) $statusText.";
            $this->bot->send($user->uid, $msg);

            // 8. Уведомление пользователя
            try {
                $userMsg = $enable
                    ? "⚡️ <b>Доступ повышен.</b> Вы назначены администратором бота."
                    : "🛡 <b>Доступ изменен.</b> Вы исключены из списка администраторов.";

                $this->bot->send($targetUser->uid, $userMsg);
            } catch (Exception $e) {
                Console::error("Ошибка уведомления $targetUsername: " . $e->getMessage());
            }
        }
    }
