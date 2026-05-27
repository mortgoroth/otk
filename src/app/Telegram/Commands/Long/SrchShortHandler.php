<?php

    namespace App\Telegram\Commands\Long;

    use App\Jobs\ExecuteSrchShort;
    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;
    use Exception;

    class SrchShortHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            // Очищаем адрес от кавычек, если они пришли из инлайна
            $location = str_replace("'", "", join(' ', $params));

            if (empty($location)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Адрес для поиска КЗ не задан.");
                return;
            }

            $this->startReply($user->uid, "⚡️ Ищу коротыши на: <code>$location</code>");
            $swlst = $this->otk->request(
                '/tg/swlist5',
                [
                    'addr' => $location
                ],
                true
            );
            if (empty($swlst['result'])) {
                $this->appendReply($user->uid, "❌ Коммутаторы по адресу <code>$location</code> не найдены.");
                return;
            }


            ExecuteSrchShort::dispatch($user, $swlst['result'], $location, $this->messageId, $this->accumulatedText);
            $this->logAction($user,'srchshort', $params);

        }

    }
