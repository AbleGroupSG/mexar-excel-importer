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
}
