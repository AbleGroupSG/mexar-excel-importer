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
            'platform_type' => Str::lower($platformInfo[0]),
            'platform_name' => $platformInfo[1],
            'status' => $platformInfo[2],
            'description' => $platformInfo[3],
            'logo' => $platformInfo[4],
        ];

        $res = $this->request('/api/v1/data/payments/platforms', 'post', $payload);

        if(isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating platform',
                        ['message' => $message, 'platform_name' => $platformInfo[1]],
                    );
                }
            }
        }
    }
}
