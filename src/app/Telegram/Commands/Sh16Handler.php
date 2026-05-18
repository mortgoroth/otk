<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;

    class Sh16Handler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // В нашем dispatch мы передадим номер калитки как параметр
            $door = $params[0] ?? null;

            $ip = match ($door) {
                '1' => '10.15.147.7',
                '2' => '10.15.147.8',
                default => null,
            };

            if (!$ip) {
                $this->bot->send($user->uid, "❌ Хрень какая-то... Неверный номер калитки.");
                return;
            }

            // 1. Пишем в чат и запоминаем ID сообщения
            $this->startReply($user->uid, "🚪 Открываю калитку $door СШ16...");

            // 2. Делаем запрос к API домофонии
            $res = $this->otk->request("/intercom/$ip/open_door");

            // 3. Анализируем ответ и дополняем сообщение (update)
            if (($res['message'] ?? '') === 'Done') {
                $this->appendReply($user->uid, "✅ Готово!");
            } else {
                $error = $res['error']['msg'] ?? 'Неизвестная ошибка';
                $this->appendReply($user->uid, "⚠️ Что-то пошло не так: ".$error);
            }
        }
    }
