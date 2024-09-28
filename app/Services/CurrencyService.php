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
//        dd($row);
        $payload = [
            'currency_id' => $this->getCurrencyId($row['currency']), // 'Currency ID',
            'use_reverse_rate' => $row['is_reverse_rate'], // 'Is Reverse Rate',
            'public_buy' => $row['buy_rate'], // 'Buy Rate',
            'public_sell' => $row['sell_rate'], // 'Sell Rate',
            'board_rate' => $row['spot_rate'], // 'Spot Rate',
            'stock_alert' => $row['stock_alert'], // 'Stock Alert',
            'max_rounding_error' => $row['max_tolerance'], // 'Max Tolerance',
            'transaction_min_amount' => $row['transaction_min_amount'], // 'Transaction Min Amount',
            'department_daily_limit' => $row['department_daily_limit'], // 'Department Daily Limit',
            'entity_daily_limit' => $row['entity_daily_limit'], // 'Entity Daily Limit',
        ];
        $res = $this->request("/api/v1/departments/$departmentID/currencies", 'post', $payload);
        if (isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                logger()->error(
                    'Error creating currency',
                    ['message' => $error['message'], 'currency_id' => $payload['currency_id'],'currency' => $row['currency']],
                );
            }
        }
    }

}
