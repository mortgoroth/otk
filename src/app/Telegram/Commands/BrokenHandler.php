<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;

    class BrokenHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Определяем действие на основе вызванной команды
            // Используем статический метод Dispatcher или свойство cmd из контекста
            // В нашем случае CommandDispatcher передает имя команды в handle (добавим этот аргумент)
            $cmdName = request()->get('command_name');

            $action = match ($cmdName) {
                'broken', 'битый' => 'add',
                'unbroken', 'небитый' => 'del',
                default => null
            };

            if (!$action) {
                $this->bot->send($user->uid, '❌ Неправильный статус порта. Используйте: broken, битый, unbroken или небитый');
                return;
            }

            // 2. Проверка параметров
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }

            $swnm = $params;
            $ports = $params;

            $txtAction = $action === 'add' ? '🛠 маркировка' : '✅ восстановление';
            $this->startReply($user->uid, "⏳ Выполняю $txtAction битых портов для <code>$swnm</code>: <b>$ports</b>...");

            // 3. Запрос к API (POST запрос)
            $res = $this->otk->request("/tg/$swnm/broken/$ports/$action", [], true);

            // 4. Обработка результата
            if (($res['error']['id'] ?? -1) === 0) {
                $this->appendReply($user->uid, "✅ ".($res['result_msg'] ?? 'Готово!'));
            } else {
                $this->appendReply($user->uid, "❌ ОШИБКА! ".($res['error']['msg'] ?? 'неизвестная ошибка'));
            }
        }
    }
