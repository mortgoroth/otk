<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class PonCompareHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $swnm = $params[0] ?? null;
            $serial = $params[1] ?? false;

            if (!$swnm) {
                $this->bot->send($user->uid, "⚠️ Коммутатор не задан");
                return;
            }

            $this->startReply($user->uid, "📊 Сравниваю PON <code>$swnm</code>".($serial ? " SN: <code>$serial</code>" : ""));

            $uri = "/switch/pon/$swnm/olt/compare".($serial ? "/$serial" : "");
            $res = app(\App\Services\Otk\OtkApiService::class)->request($uri);

            if (($res['error']['id'] ?? -1) === 0 && isset($res['result'])) {
                $rs = $res['result'];
                $out = "📡 <b>PON $swnm</b> ({$res['location']}):\n";

                foreach ($rs['sw'] as $ser => $data) {
                    $out .= "\n🏷 <code>$ser</code>\n".str_repeat("-", 20)."\n";
                    foreach ($data as $key => $val) {
                        $changed = in_array($key, $rs['compare'][$ser] ?? []);
                        $oldVal = $rs['db'][$ser][$key] ?? 'н/д';
                        $out .= " — $key: ".($changed ? "<u><b>$val</b></u> (было $oldVal)" : "$val")."\n";
                    }
                    if (empty($rs['compare'][$ser]))
                        $out .= "<i>Изменений не найдено</i>\n";
                }
                $this->appendReply($user->uid, $out);
            } else {
                $msg = ($res['error']['id'] == 3) ? "Не найдено записей в истории" : ($res['error']['msg'] ?? "Ошибка API");
                $this->appendReply($user->uid, "⚠️ $msg");
            }
        }
    }
