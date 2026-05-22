<?php

    namespace App\Services\Otk;

    use App\Services\Telegram\Console;
    use Exception;
    use Illuminate\Support\Facades\Http;

    class OtkApiService {
        protected string $baseUrl;

        public function __construct () {
            // conf('otk.api') из старого кода
            $this->baseUrl = config('services.otk.api');
        }

        public function request (string $uri, array $params = [], bool $post = false):array {
            $url = $this->baseUrl.$uri;
            Console::debug("REQUEST URL: $url");

            try {
                $request = Http::timeout(600)
                    ->connectTimeout(10); // На само соединение оставляем 10 сек

                $response = $post
                    ? $request->post($url, $params)
                    : $request->get($url, $params);
                Console::debug("RESPONSE: ".json_encode($response->body(), JSON_UNESCAPED_UNICODE));

                if ($response->failed()) {
                    Console::debug("REQUEST FAIL: $uri => {$response->body()}");
                    return [
                        'result' => false,
                        'message' => "Ошибка внешнего API: ".$response->status(),
                    ];
                }

                $data = $response->json();
                Console::debug("REQUEST DATA: ".json_encode($data, JSON_UNESCAPED_UNICODE));

                // В старом коде результат лежал в ключе 'result'
//                return $data['result'] ?? $data;
                return is_string($data) ? [$data] : $data;

            } catch (Exception $e) {
                return [
                    'result' => false,
                    'message' => 'FATAL ERROR: '.$e->getMessage(),
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Ловим именно таймаут
                return [
                    'result' => false,
                    'message' => 'Превышено время ожидания ответа от API (10 мин)'
                ];
            }
        }
    }
