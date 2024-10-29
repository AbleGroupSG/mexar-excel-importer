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

    /**
     * @throws \Throwable
     */
    public function entityCurrencyCommissionExists(int $entityID, array $commissionInfo): bool
    {
        $res = $this->request("/api/v1/crm/entities/$entityID/commissions");
        if(!empty($res['data'])) {
            foreach ($res['data'] as $datum) {
                if(
                    $datum['currency_id'] == $this->getCurrencyId($commissionInfo['currency']) &&
                    $datum['commission_type'] == $commissionInfo['commission_type'] &&
                    $datum['commission_rate'] == $commissionInfo['commission_value']
                ){
                    return true;
                }
            }
        }
        return false;
    }

    public function fetchEntityCurrencyCommission(int $entityID): array
    {
        $res = $this->request("/api/v1/crm/entities/$entityID/commissions");
        return $res['data'] ?? [];
    }
}
