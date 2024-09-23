<?php

namespace App\Services;

class BankService extends BaseService
{
    /**
     * @throws \Exception
     */
    public function createBank(array $bankInfo): void
    {
        $countryId = $this->getCountryId($bankInfo[3]);
        $payload = [
            'name' => $bankInfo[0],
            'status' => $bankInfo[1],
            'code' => $bankInfo[2],
            'country_id' => $countryId,
            'state' => $bankInfo[4],
            'city' => $bankInfo[5],
        ];

        $res = $this->request('/api/v1/stock/banks', 'post', $payload);
        if(isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating bank',
                        ['message' => $message, 'name' => $bankInfo[0], 'country' => $bankInfo[3]],
                    );
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function getCountryId(string $name): int
    {
        $list = $this->request('/api/v1/data/countries');

        foreach ($list['data'] as $country) {
            if($country['common_name'] === $name || $country['official_name'] === $name) {
                return $country['id'];
            }
        }
        return throw new \Exception('Country not found');
    }
}
