<?php

namespace App\Services;

class UserService extends BaseService
{
    public function createUser(array $userInfo): void
    {
        $payload = [
            'username' => $userInfo[0],
            'role' => 'employee',
            'email' => $userInfo[1],
            'password' => $userInfo[2],
            'first_name' => $userInfo[3],
            'last_name' => $userInfo[4],
//            'entity_id' => $userInfo[3],
        ];
        $res = $this->request('/api/v1/users', 'post', $payload);

        if(isset($res['meta']) && $res['meta']['code'] == 400) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating user',
                        ['message' => $message, 'email' => $userInfo[1], 'username' => $userInfo[0], 'entity_id' => $userInfo[3]],
                    );
                }
            }
        }

        if(isset($res['meta']) && $res['meta']['code'] !== 400) {
            logger()->info('Unexpected response', ['response' => $res]);
        }
    }
}
