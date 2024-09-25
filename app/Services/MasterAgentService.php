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
    public function prepareMasterAgent(array $masterAgentsInfo): array
    {
        $groupedEntities = [];
        $currentEntity = null;
        $service = new TransactionService();

        foreach ($masterAgentsInfo as $row) {
            if (isset($row[0]) || isset($row[1])) {
                if ($currentEntity !== null) {
                    $groupedEntities[] = $currentEntity;
                }
                $currentEntity = [
                    'department_id' => $this->getDepartmentId(),
                    'name' => $row[1],
                    'entity_id' => $row[2],
                    'enable_debt_account' => Str::lower($row[3]) === 'yes' ? 1 : 0,
                    'max_debt_credit' => $row[4],
                    'max_debt_debit' => $row[5],
                    'status' => 'active',
                    'enable_contra' => 1,
                    'description' => $row[6] ?? '',
                ];
            }

            if (!empty($row[7])) {
                $currentEntity['debit'][] = [
                    'currency_id' => $service->getCurrencyId($row[7]),
                    'balance' => $this->handleCellFormat($row[8]),
                    'average_cost' => $this->handleCellFormat($row[9]),
                ];
            }
            if (!empty($row[10])) {
                $currentEntity['credit'][] = [
                    'currency_id' => $service->getCurrencyId($row[10]),
                    'balance' => $this->handleCellFormat($row[11]),
                    'average_cost' => $this->handleCellFormat($row[12]),
                ];
            }
        }

        if ($currentEntity !== null) {
            $groupedEntities[] = $currentEntity;
        }

       return $groupedEntities;
    }
}
