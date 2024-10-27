<?php

namespace App\Services;

use Illuminate\Support\Str;

class AccountService extends BaseService
{

    /**
     * @throws \Throwable
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
                        ['message' => $message, 'account' => $payload],
                    );
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function prepareAccounts(array $accountsInfo): array
    {
        $groupedAccounts = [];
        $currentAccount = null;

        foreach ($accountsInfo as $row) {
            try {
                if (is_numeric($row['account_id'])) {
                    if ($currentAccount !== null) {
                        $groupedAccounts[] = $currentAccount;
                    }
                    $currentAccount = $this->getCurrentAccount($row);
                }

                if ($currentAccount !== null) {
                    if ($currencyId = $this->getCurrencyId($row['balance_currency'])) {
                        $currentAccount['balances'][] = [
                            'currency_id' => $currencyId,
                            'balance' => $this->handleCellFormat($row['balance']),
                            'average_cost' => $this->handleCellFormat($row['average_cost'])
                        ];
                    }
                }
            } catch (\Exception $e) {
                logger()->error($e->getMessage(), ['row' => $row]);
                continue;
            }
        }

        if ($currentAccount !== null) {
            $groupedAccounts[] = $currentAccount;
        }

        return $groupedAccounts;
    }

    /**
     * @throws \Throwable
     */
    private function getCurrentAccount(array $row):array
    {
        $accountType = Str::lower($row['account_type']);
        return match ($accountType) {
            'app' => [
                'account_name' => $row['account_name'],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $accountType,
                'platform_account_id' => Str::of($row['wallet_address'])->toString(),
                'platform_id' => $this->getPlatformId($row['app_id']),
                'entity_id' => $this->getHolderId($row['holder']),
                'operator_user_id' => $this->getOperator($row['operator']),
            ],
            'payable', 'cash' => [
                'account_name' => $row['account_name'],
                'account_number' => $row['account_number'],
                'bank_id' => $this->getBankId($row['bank_name']),
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $accountType,
                'entity_id' => $this->getHolderId($row['holder']),
                'operator_user_id' => $this->getOperator($row['operator']),
            ],
            'bank' => [
                'account_name' => $row['account_name'],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $accountType,
                'bank_id' => $this->getBankId($row['bank_name']),
                'account_number' => $row['account_number'],
                'entity_id' => $this->getHolderId($row['holder']),
                'operator_user_id' => $this->getOperator($row['operator']),
                'balances' => []
            ],
            'crypto' => [
                'account_name' => $row['account_name'],
                'department_id' => $this->getDepartmentId(),
                'calculation_method' => 'default',
                'account_type' => $accountType,
                'platform_id' => $this->getPlatformId($row['platform_name']),
                'crypto_wallet_address' => $row['wallet_address'],
                'entity_id' => $this->getHolderId($row['holder']),
                'operator_user_id' => $this->getOperator($row['operator']),
                'balances' => []
            ],
//            'virtual' => [] // TODO: implement virtual account
//            'temporary' => [] // TODO: implement temporary account
            default => throw new \Exception('Invalid account type . ' . $accountType, 400),
        };

    }

    /**
     * @throws \Throwable
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
     * @throws \Throwable
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
     * @throws \Throwable
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
     * @throws \Throwable
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
     * @throws \Throwable
     */
    public function accountExists(array $accountInfo): bool
    {
        $res = $this->request('/api/v1/stock/accounts', 'get', [
            'department_id' => $this->getDepartmentId(),
            'account_type' => Str::lower($accountInfo['account_type']),
        ]);
        if (empty($res['data'])) {
            return false;
        }
        foreach ($res['data'] as $account) {
            if (
                Str::lower($accountInfo['account_type']) === 'bank' &&
                $account['account_number'] === $accountInfo['account_number']
            ) {
                return true;
            }
            if (
                Str::lower($accountInfo['account_type']) === 'crypto' &&
                $account['crypto_wallet_address'] === $accountInfo['wallet_address']
            ) {
                return true;
            }
            if(
                Str::lower($accountInfo['account_type']) === 'payable' &&
                $account['account_number'] === $accountInfo['account_number']
            ) {
                return true;
            }

            //TODO how to check app accounts
            if (
                Str::lower($accountInfo['account_type']) === 'app'
            ) {
                return true;
            }

        }
        return false;
    }

    public function fetchAllAccounts(): array
    {
        $res = $this->request('/api/v1/stock/accounts');
        return $res['data'] ?? [];
    }


}
