<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BaseService
{
    const DEPARTMENT_ID = 1;
    /**
     * @throws \Exception
     */
    protected function request(string $uri, string $method = 'get', array $payload = []): array
    {
        $url = config('mexar.url') . $uri;
        $token = $this->getToken();

        try {
            $response = Http::withToken($token)->$method($url, $payload);
            return $response->json();
        }catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    private function getToken(): string
    {
        return Cache::rememberForever('token', function () {
            try {
                $response = Http::post(config('mexar.url') . '/api/v1/oauth/token/grant', [
                    'email' => config('mexar.email'),
                    'password' => config('mexar.password'),
                    'token_type' => 'personal'
                ]);
                if (!$response->ok()) {
                    throw new \Exception('Invalid credentials');
                }
                return $response->json()['data']['access_token'];
            }catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        });
    }

    public function removeEmptyRows(array $data):array
    {
        foreach ($data as $key => $row) {
        $isAllEmpty = true;
            foreach ($row as $item) {
                if (!empty($item)) {
                    $isAllEmpty = false;
                }
            }
            if ($isAllEmpty) {
                Arr::forget($data, $key);
            }
        }
        return $data;
    }
}
