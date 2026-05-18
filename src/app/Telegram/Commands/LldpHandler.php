<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\DebugController;

    class LldpHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка коммутатора
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params;
            $port = $params ?? false;

            // 2. Анонс поиска
            $msg = "🔍 Ищу соседей <code>$swnm</code>".($port ? " за портом <b>$port</b>" : "");
            $this->startReply($user->uid, $msg."...");

            // 3. Запрос к API
            $uri = "/tg/$swnm/lldp".($port ? "/$port" : "");
            $res = $this->otk->request($uri);

            // 4. Обработка ошибок
            $errorId = $res['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Некорректное имя коммутатора',
                    2 => '❌ Коммутатор не найден',
                    3 => '⚠️ Коммутатор недоступен',
                    4 => '❌ ОШИБКА! Порт не задан или неправильный',
                    80 => '❌ ОШИБКА! Заданный порт не магистральный',
                    81 => '📭 Соседи не найдены',
                    default => '❌ Неизвестная ошибка API'
                };

                if ($errorId === -1) {
                    DebugController::write($res, 'LLDP_ERROR');
                }

                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Формирование отчета (бывший $pars)
            if (empty($res['result'])) {
                $this->appendReply($user->uid, "📭 Соседи не найдены.");
                return;
            }

            $report = "🤝 <b>Соседи коммутатора $swnm:</b>\n\n";
            foreach ($res['result'] as $pNum => $nei) {
                $report .= "🔌 <b>Порт $pNum</b> → <code>{$nei['neiName']}</code> / <code>{$nei['neiPort']}</code>\n";
                $report .= "   └ 🏷 <i>{$nei['type']}</i> | 🌐 {$nei['addr']}\n\n";
            }

            $this->appendReply($user->uid, $report);
        }
    }
