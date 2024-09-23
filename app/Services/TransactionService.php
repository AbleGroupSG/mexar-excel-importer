<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TransactionService extends BaseService
{
    const SG_COUNTRY_ID = 220;
//    const DEPARTMENT_ID = 1;
    /**
     * @throws \Exception
     */
    public function createTransaction(array $transaction, array $entity, array $payments): array
    {
        [$toSend, $toReceive] = $this->preparePayments($payments, $transaction[0]);

        if($this->isDateColumn($transaction[1])) {
////            TODO transaction date doesnt use anywhere
            $transaction[1] = $this->parseDate($transaction[1])->format('m/d/Y');
        }

        $data = [
            'destination_country_id' => self::SG_COUNTRY_ID,
            'department_id' => self::DEPARTMENT_ID,
            'entity_id' => $entity['id'],
            'kyc_screen' => 0,
            'purpose_of_transfer' => 'others',
            'sale_user_id' => 1,
            'process_fee' => [
                'enable' => 0,
                'fee' => []
            ],
            'items' => [
                [
                    'source_currency_id' => $this->getCurrencyId($transaction[2]),
                    'target_currency_id' => $this->getCurrencyId($transaction[5]),
                    'amount' => $transaction[4],
                    'exchange_rate' => $transaction[8],
                    'exchange_rate_display' => $transaction[8],
                    'cost_rate' => $transaction[9],
                    'cost_rate_display' => $transaction[9],
                    'calculation_amount' => $transaction[7],
                ]
            ],
        ];

        if($toSend) {
            foreach ($toSend as $item) {
                $data['to_sends'][] = [
                    'method' => $item[5],
                    'channel' => $item[6],
                    'currency_id' => $this->getCurrencyId($item[3]),
                    'account_id' => $item[8], // TODO account_id ?
                    'amount' => $item[4],
    //                'remark' => '',
                    'master_agent_id' => intval($item[9]),
                    'receiver_account_id' => '', // TODO receiver_account_id == Receiver Account name ?

                ];
            }
        }

        if($toReceive) {
            foreach ($toReceive as $item) {
                $data['to_receives'][] = [
                    'method' => $item[5],
                    'channel' => $item[6],
                    'currency_id' => $this->getCurrencyId($item[3]),
                    'master_agent_id' => intval($item[9]),
                    'amount' => $item[4],
                ];
            }
        }

        return $this->request('/api/v1/remittance/create', 'post', $data);
    }

    /**
     * @throws \Exception
     */
    public function getCurrencyId(string $code): int
    {
        $list = $this->request('/api/v1/stock/currencies');

        foreach ($list['data'] as $currency) {
            if($currency['code'] === $code) {
                return $currency['id'];
            }
        }
        return throw new \Exception('Currency not found');
    }

    /**
     * @throws \Exception
     */
    public function findEntity(int $entityId): array|null
    {
        $entity = $this->request('/api/v1/crm/entities/'.$entityId);
        if (isset($entity['data'])) {
            return $entity['data'];
        }
        return null;
    }

    private function isDateColumn($value): bool
    {
        return (bool) strtotime($value) || is_numeric($value);
    }

    private function preparePayments(array $payments, int $transactionId): array
    {
        $data = [];
        foreach ($payments as $payment) {
            if (intval($payment[0]) === $transactionId) {
                $data[] = $payment;
            }
        }

        foreach ($data as $key => $value) {
            if ($this->isDateColumn( (int) $value[1])) {
                $data[$key][1] = $this->parseDate($value[1])->format('m/d/Y');
            }
        }

        $toSend = $this->getTransactionPayment($data, 'send');
        $toReceive = $this->getTransactionPayment($data, 'receive');

        return [$toSend, $toReceive];
    }

    private function getTransactionPayment(array $payments, string $type): array|bool
    {
        $payment = array_filter($payments, fn($payment) => $payment[2] === $type);

        if(empty($payment)) {
            return false;
        }

        return $payment;
    }

    private function parseDate($value)
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject($value);
        }
        return $value;
    }
}
