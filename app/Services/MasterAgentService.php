<?php
namespace App\Services;
use Exception;
use Illuminate\Support\Str;
use Throwable;

class MasterAgentService extends BaseService
{
    /**
     * @throws Exception|Throwable
     */
    public function createMasterAgent(array $masterAgent): void
    {
        $res = $this->request('/api/v1/ma', 'post', $masterAgent);
        if (isset($res['errors'])) {
            throw new Exception(json_encode($res['errors']));
        }
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function prepareMasterAgent(array $masterAgentsInfo, array $entitiesInfo): array
    {
        $groupedEntities = [];
        $currentEntity = null;
        $service = new TransactionService();

        foreach ($masterAgentsInfo as $row) {
            if (isset($row['master_agent_id']) || isset($row['name'])) {
                if ($currentEntity !== null) {
                    $groupedEntities[] = $currentEntity;
                }
                $entityId = $this->getEntityIdFromEntitiesSheet($row['entity_id'],$entitiesInfo);
                $currentEntity = [
                    'department_id' => $this->getDepartmentId(),
                    'name' => $row['name'],
                    'entity_id' => $entityId,
                    'enable_debt_account' => Str::lower($row['enable_debt']) === 'yes' ? 1 : 0,
                    'max_debt_credit' => $row['max_credit'],
                    'max_debt_debit' => $row['max_debit'],
                    'status' => 'active',
                    'enable_contra' => 1,
                    'description' => $row['description'] ?? '',
                ];
            }

            if (!empty($row['debit_currency'])) {
                $currentEntity['debit'][] = [
                    'currency_id' => $service->getCurrencyId($row['debit_currency']),
                    'balance' => abs($this->handleCellFormat($row['debit_balance'])),
                    'average_cost' => abs($this->handleCellFormat($row['debit_average_cost'])),
                ];
            }
            if (!empty($row['credit_currency'])) {
                $currentEntity['credit'][] = [
                    'currency_id' => $service->getCurrencyId($row['credit_currency']),
                    'balance' => abs($this->handleCellFormat($row['credit_balance'])),
                    'average_cost' => abs($this->handleCellFormat($row['credit_average_cost'])),
                ];
            }
        }

        if ($currentEntity !== null) {
            $groupedEntities[] = $currentEntity;
        }

       return $groupedEntities;
    }
}
