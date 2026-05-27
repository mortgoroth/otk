<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteProbePing;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;
    use Otk\Libs\Facades\DB\Tabs;

    class ProbePingHandler extends BaseHandler {

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

            ExecuteProbePing::dispatch($user, $hostData, $this->messageId, $this->accumulatedText);
            $this->appendReply($user->uid, "\n🏁 Проверка завершена.");
            $this->logAction($user, 'probe', $params);
        }
    }
