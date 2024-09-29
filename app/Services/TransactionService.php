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
    public function createTransaction(array $transaction, int $entityId, array $payments, array $masterAgents): array
    {
        [$toSend, $toReceive] = $this->preparePayments($payments, $transaction['transaction_no']);

        if($this->isDateColumn($transaction['trade_date'])) {
            $transaction['trade_date'] = $this->parseDate($transaction['trade_date'])->format('Y-m-d');
        }

        $data = [
            'destination_country_id' => $this->getDepartmentId(), // TODO: change to
            'department_id' => $this->getDepartmentId(),
            'entity_id' => $entityId,
            'kyc_screen' => 0,
            'trade_date' => $transaction['trade_date'],
            'purpose_of_transfer' => 'others',
            'sale_user_id' => 1,
            'remark' => $transaction['transaction_remark'] ?? '',
            'process_fee' => [
                'enable' => 0,
                'fee' => []
            ],
            'items' => [
                [
                    'source_currency_id' => $this->getCurrencyId($transaction['from_currency']),
                    'target_currency_id' => $this->getCurrencyId($transaction['to_currency']),
                    'amount' => $transaction['from_amount'],
                    'exchange_rate' => $transaction['exchange_rate'],
                    'exchange_rate_display' => $transaction['exchange_rate'],
                    'cost_rate' => $transaction['cost_rate'],
                    'cost_rate_display' => $transaction['cost_rate'],
                    'calculation_amount' => $transaction['to_amount'],
                ]
            ],
        ];

        $transaction = $this->request('/api/v1/remittance/create', 'post', $data);
        if(isset($transaction['transaction']['id'])){
            $transactionId = $transaction['transaction']['id'];
            if($toSend) {
                $this->handlePayment($toSend, $transactionId, 'send', $masterAgents);
            }

            if($toReceive) {
                $this->handlePayment($toReceive, $transactionId, 'receive', $masterAgents);
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
            if (intval($payment['transaction_no']) === $transactionId) {
                $data[] = $payment;
            }
        }

        foreach ($data as $key => $value) {
            if ($this->isDateColumn( (int) $value['trade_date'])) {
                $data[$key]['trade_date'] = $this->parseDate($value['trade_date'])->format('m/d/Y');
            }
        }

        $toSend = $this->getTransactionPayment($data, 'send');
        $toReceive = $this->getTransactionPayment($data, 'receive');

        return [$toSend, $toReceive];
    }

    private function getTransactionPayment(array $payments, string $type): array|bool
    {
        $payment = array_filter($payments, fn($payment) => $payment['receivesend'] === $type);

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
    private function handlePayment(array $payments, int $transactionId, string $method, array $masterAgents):void
    {
        foreach ($payments as $item) {
            $masterAgentId = $this->getMasterAgentId($masterAgents, $item['master_agent_id']);
            $paymentItem = [
                'method' => $item['payment_method'],
                'channel' => $item['channel'],
                'send_currency_id' => $this->getCurrencyId($item['currency']),
                'amount' => $item['amount'],
                'master_agent_id' => $masterAgentId,
            ];

            if ($method==='send') {
                if($paymentItem['channel'] === 'debt') {
                    $paymentItem['cost_rate'] = $item['cost_rate'];
                }
            }
             $res = $this->request(
                "/api/v1/transactions/$transactionId/payments/$method",
                'post',
                 $paymentItem
            );
            if(isset($res['errors'])) {
                throw new Exception(json_encode($res));
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function getMasterAgentId(array $masterAgents, int $masterAgentSheetId):int
    {
        foreach ($masterAgents as $masterAgent) {
            if($masterAgent['master_agent_id'] === $masterAgentSheetId) {

                $params = [
                    'name' => $masterAgent['name'],
                ];
                $res = $this->request(
                    "/api/v1/ma",
                    'get',
                    $params
                );
                if(isset($res['errors'])) {
                    throw new Exception(json_encode($res['errors']));
                }

                foreach ($res['data'] as $datum) {
                    if($datum['name'] === $masterAgent['name']) {
                        return $datum['id'];
                    }
                }
            }
        }
        return 0;
    }
}
