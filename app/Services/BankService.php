<?php

namespace App\Services;

class BankService extends BaseService
{
    /**
     * @throws \Exception
     */
    public function createBank(array $bankInfo): void
    {
        $countryId = $this->getCountryId($bankInfo['country']);
        $payload = [
            'name' => $bankInfo['bank_name'],
            'status' => $bankInfo['status'],
            'code' => $bankInfo['iban'],
            'country_id' => $countryId,
            'state' => $bankInfo['state'],
            'city' => $bankInfo['city'],
        ];

        $res = $this->request('/api/v1/stock/banks', 'post', $payload);
        if(isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating bank',
                        ['message' => $message, 'name' => $bankInfo['bank_name'], 'country' => $bankInfo['country']],
                    );
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function bankExists(array $bankInfo):bool
    {
        $res = $this->request('/api/v1/stock/banks', 'get', [
            'department_id' => $this->getDepartmentId(),
            'name' => $bankInfo['bank_name'],
        ]);
        if (empty($res['data'])) {
            return false;
        }
        foreach ($res['data'] as $bank) {
            if (
                $bank['name'] === $bankInfo['bank_name'] &&
                $bank['code'] === $bankInfo['iban']
            ) {
                return true;
            }
        }
        return false;
    }

    public function fetchAllBanks(): array
    {
        $res = $this->request('/api/v1/stock/banks');
        return $res['data'] ?? [];
    }
}
