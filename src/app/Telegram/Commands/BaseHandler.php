<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use App\Traits\Telegram\InteractsWithTelegramResponse;

    abstract class BaseHandler {

        use HasAlerts;
        use InteractsWithTelegramResponse;

        protected int $messageId = 0;
        protected string $accumulatedText = '';
        public bool $needToStore = true;

        public function __construct (
            protected Transport $bot,
            protected OtkApiService $otk
        ) {}

        abstract public function handle (UserLdap $user, array $params):void;

    }
