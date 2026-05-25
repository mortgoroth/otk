<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class PingHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];

            // 2. Сценарий А: Передан чистый IP
            if (filter_var($swnm, FILTER_VALIDATE_IP)) {
                $this->startReply($user->uid, "📡 Пингую <code>$swnm</code>...");

                // Предполагаем, что функция ping() доступна глобально или как хелпер
                // В Laravel можно использовать: exec("ping -c 1 ".escapeshellarg($swnm), $output, $result);
                $res = ping($swnm);

                $this->appendReply($user->uid, $res ? '✅ Доступен' : '❌ Недоступен');
            } // 3. Сценарий Б: Передано имя коммутатора (поиск через API)
            else {
                $this->startReply($user->uid, "🔍 Ищу <code>$swnm</code>...");

                $res = $this->otk->request("/tg/$swnm/ping");
                Console::debug("PING RESPONSE: ".json_encode($res, JSON_UNESCAPED_UNICODE));

                if (($res['error']['id'] ?? -1) === 0) {
                    $this->appendReply($user->uid, "🔎 Найден, пингую $swnm...");

                    $result = $res['result'];
                    $status = ($result['avail'] ?? false) ? '✅ Доступен' : '❌ Недоступен';

                    $reply = "<b>{$result['swnm']}</b> ({$result['swip']})\n"."Модель по БД: <code>{$result['type']}</code>>\n"."Адрес: <code>{$result['addr']}</code>\n"."Статус: $status\n";

                    if (!empty($result['nodeSwitches'])) {
                        $reply .= "\n🏢 <b>Коммутаторы в узле:</b>\n";
                        foreach ($result['nodeSwitches'] as $sw) {
                            $reply .= "• {$sw['swnm']} ({$sw['swip']}) {$sw['type']} [{$sw['topo']}]\n";
                        }
                    }

                    $this->appendReply($user->uid, $reply);
                } else {
                    $errorMsg = $res['error']['msg'] ?? 'Ошибка поиска';
                    $this->appendReply($user->uid, "❌ $errorMsg");
                }
            }
        }
    }
