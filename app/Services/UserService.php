<?php

namespace App\Services;

class UserService extends BaseService
{
    /**
     * @throws \Exception
     */
    public function createUser(array $userInfo): ?int
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

        return $res['data']['id'] ?? null;
    }

    public function addUser2Department(int $userId, int $departmentId): void
    {
        $payload = [
            'user_id' => $userId,
            'department_id' => $departmentId,
            'role'  =>  'employee',
        ];

        $res = $this->request('/api/v1/departments/' . $departmentId . '/members', 'post', $payload);

        if(isset($res['meta']) && $res['meta']['code'] == 400) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error adding user to department',
                        ['message' => $message, 'user_id' => $userId, 'department_id' => $departmentId],
                    );
                }
            }
        }

        if(isset($res['meta']) && $res['meta']['code'] !== 200) {
            logger()->info('Unexpected response', ['response' => $res]);
        }
    }

    public function fetchAllUsers()
    {
        $res = $this->request('/api/v1/users');
        return $res['data'] ?? [];
    }

    /**
     * @throws \Throwable
     */
    public function userExists(array $userInfo): bool
    {
        $res = $this->request('/api/v1/users', 'get', [
            'q' => $userInfo['username'],
            'department_id' => $this->getDepartmentId(),
        ]);
        if(empty($res['data'])) {
            return false;
        }
        foreach ($res['data'] as $user) {
            if(
                $user['username'] === $userInfo['username'] &&
                $user['email'] === $userInfo['email']
            ) {
                return true;
            }
        }
        return false;
    }
}
