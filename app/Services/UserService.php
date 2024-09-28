<?php

namespace App\Services;

class UserService extends BaseService
{
    /**
     * @throws \Exception
     */
    public function createUser(array $userInfo): void
    {
        $payload = [
            'username' => $userInfo['username'],
            'role' => 'employee',
            'email' => $userInfo['email'],
            'password' => $userInfo['password'],
            'first_name' => $userInfo['first_name'],
            'last_name' => $userInfo['last_name'],
//            'entity_id' => $userInfo[3],
        ];
        $res = $this->request('/api/v1/users', 'post', $payload);

        if(isset($res['meta']) && $res['meta']['code'] == 400) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating user',
                        ['message' => $message, 'email' => $userInfo['email'], 'username' => $userInfo['username']],
                    );
                }
            }
        }

        if(isset($res['meta']) && $res['meta']['code'] !== 400) {
            logger()->info('Unexpected response', ['response' => $res]);
        }
    }
}
