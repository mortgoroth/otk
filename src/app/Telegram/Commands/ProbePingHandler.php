<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use Otk\Libs\Facades\DB\Tabs;
    use App\Services\Otk\OtkApiService;

    class ProbePingHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Валидация входных данных
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Имя пробника не задано.");
                return;
            }

            $input = $params[0];
            if (!preg_match('/^\d{2}-\d{1,2}$/i', $input)) {
                $this->bot->send($user->uid, "❌ Некорректное имя пробника. Формат: XX-X[X]");
                return;
            }

            $probeHost = "a2-$input.probe";
            $this->startReply($user->uid, "📡 Ищу пробник <code>$probeHost</code>...");

            // 2. Получение данных из БД (через фасад Tabs)
            $probeData = Tabs::getProbesList($probeHost);

            if (!$probeData) {
                $this->appendReply($user->uid, "❌ Пробник не найден в базе мониторинга.");
                return;
            }

            $this->appendReply($user->uid, "✅ Найден: <b>{$probeData->location}</b> ({$probeData->host})\nПроверяю доступность интерфейсов...");

            // 3. Парсинг хостов внутри пробника и опрос через API
            $hostData = json_decode($probeData->host_data);
            if (empty($hostData)) {
                $this->appendReply($user->uid, "⚠️ Данные об интерфейсах пусты.");
                return;
            }

            foreach ($hostData as $datum) {
                $ip = $datum->ip_address;

                // Запрос к API для проверки пинга конкретного IP
                $isAvail = (bool) $this->otk->request("/a2/probe/$ip/ping");

                $statusIcon = $isAvail ? '🟢' : '🔴';
                $statusText = $isAvail ? 'доступен' : 'недоступен';

                $line = "$statusIcon <code>$datum->parent_host</code> / $datum->parent_port ($ip) -> $statusText";
                $this->appendReply($user->uid, $line);
            }

            $this->appendReply($user->uid, "\n🏁 Проверка завершена.");
            $this->logAction($user, 'probe', $params);
        }
    }
