<?php

namespace App\Console\Commands;

use App\Imports\ExcelImport;
use App\Services\AccountService;
use App\Services\BankService;
use App\Services\CurrencyService;
use App\Services\DepartmentService;
use App\Services\EntitiesService;
use App\Services\MasterAgentService;
use App\Services\PlatformService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;

class TestRunningCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test.running.command';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = 'excel3.xlsx';
        $data = Excel::toCollection(new ExcelImport, $path, 'public');
        $this->info('Loading...');

        $this->selectDepartmentID();

        $transactionsInfo = $data[0];
        $currencyInfo = $data[1];
        $entitiesInfo = $data[2];
        $banks = $data[5];
        $usersInfo = $data[3];
        $accounts = $data[4];
        $platforms = $data[6];
        $payments = $data[7];
        $masterAgent = $data[8];
        $entityCurrencyCommissionInfo = $data[9];

        $dataSources = [
            'Users Info'                 => ['processUsers', [$usersInfo->toArray()]],
            'Entities Info'              => ['processEntities', [$entitiesInfo->toArray()]],
            'Banks'                      => ['processBanks', [$banks->toArray()]],
            'Department Currency'        => ['processCurrencies', [$currencyInfo->toArray()]],
            'Master Agent'               => ['processMasterAgent', [$masterAgent->toArray(), $entitiesInfo->toArray()]],
            'Platforms'                  => ['processPlatforms', [$platforms->toArray()]],
            'Accounts'                   => ['processAccounts', [$accounts->toArray()]],
            'Transactions Info'          => ['processTransactions', [
                $transactionsInfo->toArray(),
                $payments->toArray(),
                $entitiesInfo->toArray(),
                $masterAgent->toArray()
            ]],
            'Entity Currency Commission' => ['processEntityCurrencyCommission', [
                $entityCurrencyCommissionInfo->toArray(),
                $entitiesInfo->toArray()
            ]],
        ];

        $this->showAndProcessSheetsOptions($dataSources);
    }

    private function processUsers(array $usersInfo): array
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);
        foreach ($usersInfo as &$userInfo) {
            $isStored = $service->userExists($userInfo);
            $userInfo['is_stored'] = $isStored;
        }
        return $usersInfo;
    }
    private function processEntities(array $entitiesInfo): array
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);
        foreach ($entitiesInfo as &$entityInfo) {
            $isStored = $service->entityExists($entityInfo);
            $entityInfo['is_stored'] = $isStored;
        }
        return $entitiesInfo;
    }
    private function processBanks(array $banksInfo): array
    {
        $service = new BankService();
        $banksInfo = $service->removeEmptyRows($banksInfo);
        foreach ($banksInfo as &$bank) {
            $isStored = $service->bankExists($bank);
            $bank['is_stored'] = $isStored;
        }
        return $banksInfo;
    }
    private function processCurrencies(array $currenciesInfo): array
    {
        $service = new CurrencyService();
        foreach ($currenciesInfo as &$currencyInfo) {
            $isStored = $service->currencyExists($currencyInfo);
            $currencyInfo['is_stored'] = $isStored;
        }
        return $currenciesInfo;
    }
    private function processMasterAgent(array $masterAgentInfo): array
    {
        $service = new MasterAgentService();
        foreach ($masterAgentInfo as &$agentInfo) {
            $isStored = $service->masterAgentExists($agentInfo);
            $agentInfo['is_stored'] = $isStored;
        }
        return $masterAgentInfo;
    }
    private function processPlatforms(array $platformsInfo): array
    {
        $service = new PlatformService();
        foreach ($platformsInfo as &$platformInfo) {
            $isStored = $service->platformExists($platformInfo);
            $platformInfo['is_stored'] = $isStored;
        }

        return $platformsInfo;
    }

    /**
     * @throws \Throwable
     */
    private function processAccounts(array $accountsInfo): array
    {
        // TODO NOT COMPLETE
        $service = new AccountService();
        foreach ($accountsInfo as &$accountInfo) {
            $isStored = $service->accountExists($accountInfo);
            $accountInfo['is_stored'] = $isStored;
        }
        return $accountsInfo;
    }
    private function processTransactions(array $transactionsInfo): array
    {
        $service = new TransactionService();
        return $transactionsInfo;
    }

    private function  selectDepartmentID(): void
    {
        $menuBuilder = (new CliMenuBuilder)
            ->setTitle('Select department ID:');

        $service = new DepartmentService();

        $departments = $service->getDepartments();
        if(empty($departments) || !isset($departments['data'])) {
            $this->error('No departments found');
            return;
        }

        foreach ($departments['data'] as $department) {
            $departmentId = $department['id'];
            $menuBuilder->addRadioItem('Department '. $departmentId . $department['name'], function(CliMenu $menu) use ($departmentId) {
                Cache::put('departmentId', (int) $departmentId, now()->addDay());
                $this->info('Department ID selected: ' . $departmentId);
                $menu->close();
            });
        }

        $menu = $menuBuilder
            ->disableDefaultItems()
            ->build();

        $menu->open();
    }
}
