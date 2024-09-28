<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class TransactionService extends BaseService
{
    const SG_COUNTRY_ID = 220;
//    const DEPARTMENT_ID = 1;

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function createTransaction(array $transaction, int $entityId, array $payments): array
    {
        [$toSend, $toReceive] = $this->preparePayments($payments, $transaction[0]);

        if($this->isDateColumn($transaction[1])) {
            $transaction[1] = $this->parseDate($transaction[1])->format('Y-m-d');
        }

        $data = [
            'destination_country_id' => $this->getDepartmentId(),
            'department_id' => $this->getDepartmentId(),
            'entity_id' => $entityId,
            'kyc_screen' => 0,
            'trade_date' => $transaction[1],
            'purpose_of_transfer' => 'others',
            'sale_user_id' => 1,
            'remark' => $transaction[11] ?? '',
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

        $transaction = $this->request('/api/v1/remittance/create', 'post', $data);
        if(isset($transaction['transaction']['id'])){
            $transactionId = $transaction['transaction']['id'];
            if($toSend) {
                $this->handleToSend($toSend, $transactionId);
            }

            if($toReceive) {
                $this->handleToReceive($toReceive, $transactionId);
            }

        }else {
            throw new Exception(json_encode($transaction));
        }


        return $transaction;
    }

    /**
     * @throws Throwable
     */
    public function completeTransaction(int $transactionId): void
    {
        $res = $this->request("/api/v1/transactions/$transactionId/actions/complete", 'post');
        dump($res);
        if (isset($res['errors'])) {
            throw new \Exception(json_encode($res['errors']));
        }
    }


    /**
     * @throws Exception|Throwable
     */
    public function findEntity(int|string $entityInfo): array|null
    {
        if (is_numeric($entityInfo) && (string)(int)$entityInfo === (string)$entityInfo) {
            $entityInfo = (int)$entityInfo;
        }
        if(is_int($entityInfo)) {
            $entity = $this->request('/api/v1/crm/entities/'.$entityInfo);
            if (isset($entity['data'])) {
                return $entity['data'];
            }
        }else{
            $entities = $this->request('/api/v1/crm/entities', 'get', ['q' => $entityInfo]);
            if (isset($entities['data'])) {
                foreach ($entities['data'] as $entity) {
                    if ($entity['entity_type'] === 'individual' && $entity['first_name'] === $entityInfo) {
                        return $entity;
                    }
                    if ($entity['entity_type'] === 'corporate' && $entity['name'] === $entityInfo) {
                        return $entity;
                    }
                }
                return null;
            }
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

    private function parseDate($value): \DateTime
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject($value);
        }
        return $value;
    }

    /**
     * @throws Throwable
     */
    private function handleToSend(array $toSend, int $transactionId):void
    {
        foreach ($toSend as $item) {
            $toSendItem = [
                'method' => $item[5],
                'channel' => $item[6],
                'send_currency_id' => $this->getCurrencyId($item[3]),
                'amount' => $item[4],
                'master_agent_id' => intval($item[9]),
            ];
            if($toSendItem['channel'] === 'debt') {
                $toSendItem['cost_rate'] = $item[10];
            }
             $res = $this->request(
                "/api/v1/transactions/$transactionId/payments/send",
                'post',
                $toSendItem
            );
            if(isset($res['errors'])) {
                throw new Exception(json_encode($res));
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function handleToReceive(array $toReceive, int $transactionId):void
    {
        foreach ($toReceive as $item) {
            $toReceiveItem = [
                'method' => $item[5],
                'channel' => $item[6],
                'currency_id' => $this->getCurrencyId($item[3]),
                'master_agent_id' => intval($item[9]),
                'amount' => $item[4],
            ];

            $res = $this->request(
                "/api/v1/transactions/$transactionId/payments/receive",
                'post',
                $toReceiveItem
            );
            if(isset($res['errors'])) {
                throw new Exception(json_encode($res));
            }
        }
    }
}
