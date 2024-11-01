<?php

namespace App\Services;

use Illuminate\Support\Str;

class PlatformService extends BaseService
{
    /**
     * @throws \Exception
     */
    public function createPlatform(array $platformInfo): void
    {
        $payload = [
            'platform_type' => Str::lower($platformInfo['platform_type']),
            'platform_name' => $platformInfo['platform_name'],
            'status' => $platformInfo['status'],
            'description' => $platformInfo['description'],
            'logo' => $platformInfo['logo'],
        ];

        $res = $this->request('/api/v1/data/payments/platforms', 'post', $payload);
        if(isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating platform',
                        ['message' => $message, 'platform_name' => $platformInfo['platform_name']],
                    );
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function platformExists(array $platformInfo): bool
    {
        $res = $this->request('/api/v1/data/payments/platforms', 'get', [
            'platform_name' => $platformInfo['platform_name'],
        ]);
        if (empty($res['data'])) {
            return false;
        }
        foreach ($res['data'] as $platform) {
            if (
                $platform['platform_name'] === $platformInfo['platform_name']
            ) {
                return true;
            }
        }
        return false;
    }

    public function fetchAllPlatforms(): array
    {
        $res = $this->request('/api/v1/data/payments/platforms');
        return $res['data'] ?? [];
    }
}
