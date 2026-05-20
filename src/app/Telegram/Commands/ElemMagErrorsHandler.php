<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class ElemMagErrorsHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $elem = $params[0] ?? null;
            if (!$elem) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Элемент не задан.");
                return;
            }

            $this->startReply($user->uid, "📉 Поиск ошибок на магистралях в элементе <b>$elem</b>...");

            $res = $this->otk->request("/elem/$elem/avail");

            if (isset($res['error']['id']) && $res['error']['id'] > 0) {
                $this->appendReply($user->uid, "❌ Ошибка поиска коммутаторов: ".($res['error']['msg'] ?? ''));
                return;
            }

            $unavl = $res['result']['unavail'] ?? [];
            $avail = $res['result']['avail'] ?? [];

            $this->appendReply($user->uid, "🚫 <b>Недоступные коммутаторы:</b>");
            if (!empty($unavl)) {
                $this->appendReply($user->uid, " • ".implode("\n • ", $unavl));
            } else {
                $this->appendReply($user->uid, " — отсутствуют.");
            }

            $this->appendReply($user->uid, "🔄 Ищу ошибки на магистралях (до 60 сек)...");

            $elemerr = $this->otk->request("/elem/$elem/errors", [
                'elem' => $elem, 'avail' => $avail, 'timeout' => 60
            ]);

            if (!$elemerr || ($elemerr['error']['id'] ?? -1) !== 0) {
                $this->appendReply($user->uid, "❌ Ошибка API: ".($elemerr['error']['msg'] ?? 'timeout?'));
                return;
            }

            // Вывод вердиктов
            if (!empty($elemerr['verdict'])) {
                foreach ($elemerr['verdict'] as $v) {
                    $this->appendReply($user->uid, ($v === 'Отсутствуют' ? '✅ Ошибок не найдено' : "⚠️ $v"));
                }
            }

            // Ошибки А3
            if (!empty($elemerr['verdict_a3'])) {
                $this->appendReply($user->uid, "\n🏢 <b>Ошибки на А3:</b>\n".implode("\n", $elemerr['verdict_a3']));
            }

            // Пропущенные
            if (!empty($elemerr['skipped'])) {
                $skipped = "⚠️ <b>Пропущенные:</b>\n";
                foreach ($elemerr['skipped'] as $sw => $reason) {
                    $skipped .= " • $sw: $reason\n";
                }
                $this->appendReply($user->uid, $skipped);
            }

            $this->bot->send($user->uid, "🏁 Проверка элемента $elem завершена!");
            $this->logAction($user,'elemerr', $params);

        }
    }
