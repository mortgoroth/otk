<?php

    namespace App\Telegram\Commands\Admin;

    use App\Models\UserLdap;
    use App\Services\Telegram\BotEngine;
    use App\Services\Telegram\Console;
    use App\Telegram\Commands\BaseHandler;
    use Exception;
    use Illuminate\Container\EntryNotFoundException;
    use Illuminate\Contracts\Container\CircularDependencyException;
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\NotFoundExceptionInterface;

    class AlertManageHandler extends BaseHandler {

        public bool $needToStore = false;

        /**
         * @throws CircularDependencyException
         * @throws EntryNotFoundException
         * @throws NotFoundExceptionInterface
         * @throws ContainerExceptionInterface
         */
        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка прав (только админы и добавленные в список рассылок могут управлять назначением других админов других)
            if (!$user->alert && !$user->is_admin) {
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
            $targetUser = UserLdap::whereUsername($targetUsername)
                ->first();

            if (!$targetUser) {
                $this->bot->send($user->uid, "❌ Пользователь <code>$targetUsername</code> не найден в базе бота.");
                return;
            }

            // 5. Обновление статуса
            $targetUser->update(['alert' => $enable]);

            // 6. Ответ админу
            $msg = "🔔 Уведомления для <b>$targetUser->ldap_full_name</b> (@$targetUser->tg_full_name) $statusText.";
            $this->bot->send($user->uid, $msg);

            // 7. Уведомление пользователя и ОБНОВЛЕНИЕ КЛАВИАТУРЫ
            try {
                // Получаем доступ к BotEngine для пересчета кнопок
                $engine = app(BotEngine::class);
                $newKeyboard = $engine->renderKeyboard($targetUser);

                $userMsg = $enable
                    ? "🚀 Вам включены системные уведомления о действиях инженеров."
                    : "🔕 Системные уведомления для вас отключены.";

                // Отправляем сообщение с НОВОЙ клавиатурой
                $this->bot->send($targetUser->uid, $userMsg, $newKeyboard);

                // Если текущий юзер редактировал САМ СЕБЯ, обновляем статику в Транспорте обратно
                if ($targetUser->uid === $user->uid) {
                    $engine->renderKeyboard($user);
                }

            } catch (Exception $e) {
                Console::error("Ошибка обновления кнопок для $targetUsername: " . $e->getMessage());
            }
        }
    }
