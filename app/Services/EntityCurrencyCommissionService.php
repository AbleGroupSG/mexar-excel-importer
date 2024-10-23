<?php

namespace App\Services;

class EntityCurrencyCommissionService extends BaseService
{
    /**
     * @throws \Throwable
     */
    public function createOrUpdateCommission(array $commissionData, array $entity): void
    {
        $service = new CurrencyService();
        $currencyId = $service->getCurrencyId($commissionData['currency']);
        $entityID = $entity['id'];
        $payload = [
            'department_id' => $this->getDepartmentId(),
            'currency_id' => $currencyId,
            'commission_type' => $commissionData['commission_type'],
            'commission_rate' => $commissionData['commission_value'],
        ];

        $res = $this->request("/api/v1/crm/entities/$entityID/commissions", 'post', $payload);
        if(isset($res['errors'])) {
            $errors = $res['errors'];
            logger()->error(
                'Error creating commission',
                ['message' => $errors, 'payload' => $payload],
            );
        }
    }
}
