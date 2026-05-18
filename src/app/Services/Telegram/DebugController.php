<?php

    namespace App\Services\Telegram;

    use Illuminate\Support\Facades\Log;

    class DebugController {
        public static function write ($msg, string $prefix = ''):void {
            if (!config('app.debug'))
                return;

            if (is_array($msg) || is_object($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1]['class'] ?? 'unknown';
            $method = $trace[1]['function'] ?? 'unknown';

            Log::channel('telegram')
                ->info("[$caller@$method] $prefix: $msg");
        }
    }
