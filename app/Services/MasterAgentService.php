<?php
namespace App\Services;
use Illuminate\Support\Str;

class MasterAgentService extends BaseService
{
    public function createMasterAgent(array $masterAgent): void
    {
        $res = $this->request('/api/v1/ma', 'post', $masterAgent);
        if (isset($res['errors'])) {
            $errors = $res['errors'];
            foreach ($errors as $error) {
                foreach ($error as $message) {
                    logger()->error(
                        'Error creating master agent',
                        ['message' => $message, 'name' => $masterAgent['name']],
                    );
                }
            }
        }
    }

    /**
     * @throws \Exception
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
                    'department_id' => self::DEPARTMENT_ID,
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
                    'balance' => $row[8],
                    'average_cost' => $row[9],
                ];
            }
            if (!empty($row[10])) {
                $currentEntity['credit'][] = [
                    'currency_id' => $service->getCurrencyId($row[10]),
                    'balance' => $row[11] ?? 0,
                    'average_cost' => $row[12] ?? 0,
                ];
            }
        }

        if ($currentEntity !== null) {
            $groupedEntities[] = $currentEntity;
        }

       return $groupedEntities;
    }
}
