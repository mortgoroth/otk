<?php

    namespace App\Traits\Telegram;

    trait SendsLongMessages {
        protected function splitAndSend (string $text, array $params, callable $sendMethod):void {
            if (mb_strlen($text) <= 4000) {
                $sendMethod($params);
                return;
            }

            // Разбиваем по строкам, чтобы не портить теги
            $lines = explode("\n", $text);
            $currentPart = "";

            foreach ($lines as $line) {
                if (mb_strlen($currentPart.$line."\n") > 4000) {
                    $params['text'] = $currentPart;
                    $sendMethod($params);
                    $currentPart = $line."\n";
                } else {
                    $currentPart .= $line."\n";
                }
            }

            if (!empty($currentPart)) {
                $params['text'] = $currentPart;
                $sendMethod($params);
            }
        }
    }
