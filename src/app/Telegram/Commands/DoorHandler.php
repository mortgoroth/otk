<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class DoorHandler extends BaseHandler {
        public bool $needToStore = true;

        protected array $doors = [
            ['ip' => '10.15.26.14', 'addr' => 'ББ208/1,домофон'],
            ['ip' => '10.15.147.7', 'addr' => 'СШ16,Калитка1'],
            ['ip' => '10.15.147.8', 'addr' => 'СШ16,Калитка2'],
        ];

        public function handle (UserLdap $user, array $params):void {
            Console::debug("DOORS_LIST => $this->doors");

            // 1. Если калитка выбрана (есть параметр)
            if (isset($params[0])) {
                $doorAddr = $params[0];
                $foundDoor = collect($this->doors)->firstWhere('addr', $doorAddr);

                if (!$foundDoor) {
                    $this->bot->send($user->uid, "❌ Хрень какая-то... Калитка не найдена.");
                    return;
                }

                $ip = $foundDoor['ip'];
                $addr = $foundDoor['addr'];

                Console::debug("DOOR_FOUND => $ip, $addr");

                // Начинаем процесс открытия
                $this->startReply($user->uid, "🚪 Открываю <b>$addr</b>...");

                // Запрос в API (второй параметр true делает POST запрос)
                $res = $this->otk->request("/intercom/$ip/open_door", [], true);

                if (($res['message'] ?? '') === 'Done') {
                    $this->appendReply($user->uid, "✅ Готово!");
                } else {
                    $error = $res['error']['msg'] ?? 'неизвестная ошибка';
                    $this->appendReply($user->uid, "⚠️ Что-то пошло не так: ".$error);
                }
            } // 2. Если параметров нет — выводим список кнопок
            else {
                $inline = [];
                foreach ($this->doors as $doorData) {
                    $inline[] = [
                        [
                            'text' => $doorData['addr'], 'callback_data' => "/door ".$doorData['addr']
                        ]
                    ];
                }

                Console::debug("DOOR_INLINE => $inline");
                $this->bot->sendInline($user->uid, 'Доступные калитки:', $inline);
            }
            $this->logAction($user,'door', $params);
        }
    }
