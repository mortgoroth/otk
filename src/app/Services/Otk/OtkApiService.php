<?php

    namespace App\Services\Otk;

    use App\Services\Telegram\Console;
    use Illuminate\Support\Facades\Http;
    use App\Services\Telegram\DebugController;

    class OtkApiService {
        protected string $baseUrl;

        public function __construct () {
            // conf('otk.api') из старого кода
            $this->baseUrl = config('services.otk.api');
        }

        public function request (string $uri, array $params = [], bool $post = false):array {
            $url = $this->baseUrl.$uri;
            Console::warn("REQUEST URL: $url");

            try {
                $request = Http::timeout(600)
                    ->connectTimeout(10); // На само соединение оставляем 10 сек

                $response = $post
                    ? $request->post($url, $params)
                    : $request->get($url, $params);
                Console::warn("REQUEST RESPONSE: ".json_encode($response, JSON_UNESCAPED_UNICODE));

                if ($response->failed()) {
                    DebugController::write($response->body(), "OTK_API_ERROR: $uri");
                    return [
                        'result' => false,
                        'message' => "Ошибка внешнего API: ".$response->status(),
                    ];
                }

                $data = $response->json();
                Console::warn("REQUEST DATA: ".json_encode($data, JSON_UNESCAPED_UNICODE));

                // В старом коде результат лежал в ключе 'result'
                return $data['result'] ?? $data;

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Ловим именно таймаут
                return [
                    'result' => false,
                    'message' => 'Превышено время ожидания ответа от API (10 мин)'
                ];
            }
        }
    }
