<?php

namespace App\Services;

use Illuminate\Support\Str;

class CurrencyService extends BaseService
{


    /**
     * @throws \Throwable
     */
    public function createCurrency(array $row, int $departmentID): void
    {
        $payload = [
            'currency_id' => $this->getCurrencyId($row[0]), // 'Currency ID',
            'use_reverse_rate' => $row[4], // 'Is Reverse Rate',
            'public_buy' => $row[1], // 'Buy Rate',
            'public_sell' => $row[2], // 'Sell Rate',
            'board_rate' => $row[3], // 'Spot Rate',
            'stock_alert' => $row[6], // 'Stock Alert',
            'max_rounding_error' => $row[5], // 'Max Tolerance',
            'transaction_min_amount' => $row[7], // 'Transaction Min Amount',
            'department_daily_limit' => $row[8], // 'Department Daily Limit',
            'entity_daily_limit' => $row[9], // 'Entity Daily Limit',
        ];
        $res = $this->request("/api/v1/departments/$departmentID/currencies", 'post', $payload);
        if (isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating currency',
                        ['message' => $message, 'currency_id' => $payload['currency_id'],'currency' => $row[0]],
                    );
                }
            }
        }
    }

}
