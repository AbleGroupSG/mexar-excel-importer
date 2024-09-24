<?php

namespace App\Services;

use Illuminate\Support\Str;

class AccountService extends BaseService
{

    /**
     * @throws \Exception
     */
    public function createAccount(array $payload): void
    {
        $res = $this->request('/api/v1/stock/accounts', 'post', $payload);
        if(isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating account',
                        ['message' => $message, 'account_name' => $payload['account_name']],
                    );
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function prepareAccounts(array $accountsInfo):array
    {
        $groupedAccounts = [];
        $currentAccount = null;

        foreach ($accountsInfo as $row) {
            if (is_numeric($row[0])) {
                if ($currentAccount !== null) {
                    $groupedAccounts[] = $currentAccount;
                }
                $currentAccount = $this->getCurrentAccount($row);

            }

            if ($currentAccount !== null) {
                if ($currencyId = $this->getCurrencyId($row[10])) {
                    $currentAccount['balances'][] = [
                        'currency_id' => $currencyId,
                        'balance' => $row[11],
                        'average_cost' => $row[12]
                    ];
                }
            }
        }

        if ($currentAccount !== null) {
            $groupedAccounts[] = $currentAccount;
        }
        return $groupedAccounts;
    }

    /**
     * @throws \Exception
     */
    private function getCurrentAccount(array $row):array
    {
        $accountType = Str::lower($row[2]);
        return match ($accountType) {
            'app' => [
                'account_name' => $row[1],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $accountType,
                'platform_account_id' => Str::of($row[6])->toString(),
                'platform_id' => $this->getPlatformId($row[5]),
                'entity_id' => $this->getHolderId($row[8]),
                'operator_user_id' => $this->getOperator($row[9]),
            ],
            'payable', 'cash' => [
                'account_name' => $row[1],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $row[2],
                'entity_id' => $this->getHolderId($row[8]),
                'operator_user_id' => $this->getOperator($row[9]),
            ],
            'bank' => [
                'account_name' => $row[1],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $row[2],
                'bank_id' => $this->getBankId($row[3]),
                'account_number' => $row[4],
                'entity_id' => $this->getHolderId($row[8]),
                'operator_user_id' => $this->getOperator($row[9]),
                'balances' => []
            ],
            'crypto' => [
                'account_name' => $row[1],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $row[2],
                'platform_id' => $this->getPlatformId($row[5]),
                'crypto_wallet_address' => $row[7],
                'entity_id' => $this->getHolderId($row[8]),
                'operator_user_id' => $this->getOperator($row[9]),
                'balances' => []
            ],
//            'virtual' => [] // TODO: implement virtual account
//            'temporary' => [] // TODO: implement temporary account
            default => throw new \Exception('Invalid account type', 400),
        };

    }

    /**
     * @throws \Exception
     */
    private function getBankId(string|null $name):int|null
    {
        if($name === null) {
            return null;
        }
        $res = $this->request('/api/v1/stock/banks', 'get', ['name' => $name]);
        if(isset($res['data'][0]['id'])) {
            return $res['data'][0]['id'];
        }

        throw new \Exception('Bank not found', 404);
    }

    /**
     * @throws \Exception
     */
    private function getPlatformId(string|null $name ):int|null
    {
        if($name === null) {
            return null;
        }
        $res = $this->request('/api/v1/data/payments/platforms', 'get', ['q' => $name]);
        if(isset($res['data'][0]['id'])) {
            return $res['data'][0]['id'];
        }
        throw new \Exception('Platform not found', 404);
    }

    /**
     * @throws \Exception
     */
    private function getHolderId(string|null $name ):int|null
    {
        if($name === null) {
            return null;
        }

        if(Str::contains($name, ' ')) {
            [$firstName, $lastName] = explode(' ', $name);
        }else {
            $firstName = $name;
        }

        $res = $this->request('/api/v1/crm/entities', 'get', ['q' => $firstName]);
        if ($res['data']) {
            if (empty($res['data'])) {
                throw new \Exception('Holder not found', 404);
            }

            if(isset($lastName)) {
                foreach ($res['data'] as $entity) {
                    if ($entity['first_name'] === $firstName && $entity['last_name'] === $lastName) {
                        return $entity['id'];
                    }
                }
            }

            foreach ($res['data'] as $entity) {
                if ($entity['first_name'] === $firstName) {
                    return $entity['id'];
                }
            }

        }

        throw new \Exception('Holder not found', 404);
    }

    /**
     * @throws \Exception
     */
    public function getOperator(string $username): int
    {
        $res = $this->request('/api/v1/users', 'get', ['q' => $username]);
        if(isset($res['data'])) {
            foreach ($res['data'] as $user) {
                if($user['username'] === $username) {
                    return $user['id'];
                }
            }
        }
        throw new \Exception('Operator not found', 404);
    }

    /**
     * @throws \Exception
     */
    private function getCurrencyId(string|null $currency):int|null
    {
        if($currency === null) {
            return null;
        }
        $service = new TransactionService();
        return $service->getCurrencyId($currency);
    }

}
