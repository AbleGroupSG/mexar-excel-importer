<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class BaseService
{
    const DEPARTMENT_ID = 1;
    /**
     * @throws Exception|Throwable
     */
    protected function request(string $uri, string $method = 'get', array $payload = []): array
    {
        $url = config('mexar.url') . $uri;
        $token = $this->getToken();

        try {
            return retry(5, function () use ($url, $token, $method, $payload) {
                $response = Http::withToken($token)->timeout(15)->$method($url, $payload);

                if (in_array($response->status(), [400, 422])) {
                    return $response->json();
                }

                if (!$response->successful()) {
                    throw new RequestException($response);
                }

                return $response->json();
            }, 1000, function ($exception) {
                return $exception instanceof ConnectionException;
            });
        } catch (ConnectionException $e) {
            throw new Exception('Request timed out after 5 attempts.');
        } catch (RequestException $e) {
            return $e->response->json();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    protected function getDepartmentId(): int
    {
        return Cache::get('departmentId', 1);
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
                    throw new Exception('Invalid credentials');
                }
                return $response->json()['data']['access_token'];
            }catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        });
    }

    /**
     * @throws Exception|Throwable
     */
    public function getCurrencyId(string|null $code): int|null
    {
        if($code === null) {
            return null;
        }
        $list = $this->request('/api/v1/stock/currencies');
        if(!isset($list['data'])) {
            logger()->error('Currency list is empty', ['response' => $list]);
            throw new Exception('Currency list is empty');
        }
        foreach ($list['data'] as $currency) {
            if($currency['code'] === $code) {
                return $currency['id'];
            }
        }
        return throw new Exception('Currency not found');
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

    protected function handleCellFormat(mixed $value): mixed
    {
        return floatval(str_replace(',', '.', $value));
    }

    /**
     * Find entity ID from the API
     * 
     * @param int|null $entityId the entityId from the wroksheet
     * @param array $entitiesInfo the parsed entities sheet
     * 
     * @throws Throwable
     */
    public function getEntityIdFromEntitiesSheet(int|null $entityId, array $entitiesInfo): int
    {
        if ($entityId === null) {            
            logger()->debug('Entity ID for master agent is empty');
            throw new Exception('Entity ID for master agent is empty');
        }
        $service = new EntitiesService();
        $row = [];
        foreach ($entitiesInfo as $entity) {
            if ((int) $entity['id'] === $entityId) {
                $row = $entity;
                break;
            }
        }
        if(empty($row)) {
            logger()->debug('Entity ID for master agent is not found in entities sheet');
            throw new Exception('Entity ID for master agent is not found in entities sheet');
        }
        $entity = $service->findOrCreateEntity($row);
        return $entity['id'];
    }
}
