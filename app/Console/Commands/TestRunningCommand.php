<?php

namespace App\Console\Commands;

use App\Exports\FileExport;
use App\Imports\ExcelImport;
use App\Services\AccountService;
use App\Services\BankService;
use App\Services\CurrencyService;
use App\Services\DepartmentService;
use App\Services\EntitiesService;
use App\Services\EntityCurrencyCommissionService;
use App\Services\MasterAgentService;
use App\Services\PlatformService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
        $banksInfo = $data[5];
        $usersInfo = $data[3];
        $accountsInfo = $data[4];
        $platformsInfo = $data[6];
        $paymentsInfo = $data[7];
        $masterAgentInfo = $data[8];
        $entityCurrencyCommissionInfo = $data[9];


        $dataSources = [
            'Users'                 => ['processUsers', [$usersInfo->toArray()]],
            'Entities'              => ['processEntities', [$entitiesInfo->toArray()]],
            'Banks'                      => ['processBanks', [$banksInfo->toArray()]],
            'Department Currency'        => ['processCurrencies', [$currencyInfo->toArray()]],
            'Master Agent'               => ['processMasterAgent', [$masterAgentInfo->toArray(), $entitiesInfo->toArray()]],
            'Platforms'                  => ['processPlatforms', [$platformsInfo->toArray()]],
            'Accounts'                   => ['processAccounts', [$accountsInfo->toArray()]],
            'Transactions'          => ['processTransactions', [
                $transactionsInfo->toArray(),
                $entitiesInfo->toArray(),
            ]],
            'Entity Currency Commission' => ['processEntityCurrencyCommission', [
                $entityCurrencyCommissionInfo->toArray(),
                $entitiesInfo->toArray()
            ]],
        ];

        $this->showAndProcessSheetsOptions($dataSources);
    }

    private function processUsers(array $usersInfo): void
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);
        $this->saveHeader($usersInfo[0], 'users');
        foreach ($usersInfo as &$userInfo) {
            try {
                $isStored = $service->userExists($userInfo);
                $userInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e) {
                logger()->error($e->getMessage(), $usersInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('usersInfo', $usersInfo, now()->addDay());
    }
    private function processEntities(array $entitiesInfo): void
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);
        $this->saveHeader($entitiesInfo[0], 'entities');
        foreach ($entitiesInfo as &$entityInfo) {
            try {
                $isStored = $service->entityExists($entityInfo);
                $entityInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e){
                logger()->error($e->getMessage(), $entityInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('entitiesInfo', $entitiesInfo, now()->addDay());
    }
    private function processBanks(array $banksInfo): void
    {
        $service = new BankService();
        $banksInfo = $service->removeEmptyRows($banksInfo);
        $this->saveHeader($banksInfo[0], 'banks');
        foreach ($banksInfo as &$bank) {
            try{
                $isStored = $service->bankExists($bank);
                $bank['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e){
                logger()->error($e->getMessage(), $bank);
                $this->error($e->getMessage());
            }
        }
        Cache::put('banksInfo', $banksInfo, now()->addDay());
    }
    private function processCurrencies(array $currenciesInfo): void
    {
        $service = new CurrencyService();
        $currenciesInfo = $service->removeEmptyRows($currenciesInfo);
        $this->saveHeader($currenciesInfo[0], 'currencies');
        foreach ($currenciesInfo as &$currencyInfo) {
            try{
                $isStored = $service->currencyExists($currencyInfo);
                $currencyInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e) {
                logger()->error($e->getMessage(), $currencyInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('currenciesInfo', $currenciesInfo, now()->addDay());
    }
    private function processMasterAgent(array $masterAgentInfo): void
    {
        $service = new MasterAgentService();
        $masterAgentInfo = $service->removeEmptyRows($masterAgentInfo);
        $this->saveHeader($masterAgentInfo[0], 'master_agents');
        foreach ($masterAgentInfo as &$agentInfo) {
            try{
                $isStored = $service->masterAgentExists($agentInfo);
                $agentInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e) {
                logger()->error($e->getMessage(), $agentInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('masterAgentInfo', $masterAgentInfo, now()->addDay());
    }
    private function processPlatforms(array $platformsInfo): void
    {
        $service = new PlatformService();
        $platformsInfo = $service->removeEmptyRows($platformsInfo);
        $this->saveHeader($platformsInfo[0], 'platforms');
        foreach ($platformsInfo as &$platformInfo) {
            try{
                $isStored = $service->platformExists($platformInfo);
                $platformInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e){
                logger()->error($e->getMessage(), $platformInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('platformsInfo', $platformsInfo, now()->addDay());
    }

    private function processAccounts(array $accountsInfo): void
    {
        // TODO NOT COMPLETE
        $service = new AccountService();
        $accountsInfo = $service->removeEmptyRows($accountsInfo);
        $this->saveHeader($accountsInfo[0], 'accounts');
        foreach ($accountsInfo as &$accountInfo) {
            try {
                $isStored = $service->accountExists($accountInfo);
                $accountInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e) {
                logger()->error($e->getMessage(), $accountInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('accountsInfo', $accountsInfo, now()->addDay());
    }
    private function processTransactions(array $transactionsInfo, array $entitiesInfo): void
    {
        // TODO NOT COMPLETE
        $service = new TransactionService();
        $transactionsInfo = $service->removeEmptyRows($transactionsInfo);
        $this->saveHeader($transactionsInfo[0], 'transactions');
        foreach ($transactionsInfo as &$transactionInfo) {
            try{
                $isStored = $service->transactionExists($transactionInfo, $entitiesInfo);
                $transactionInfo['is_stored'] = $isStored ? 'yes': 'no';
            }catch (\Throwable $e){
                logger()->error($e->getMessage(), $transactionInfo);
                $this->error($e->getMessage());
            }
        }
        Cache::put('transactionsInfo', $transactionsInfo, now()->addDay());
    }
    private function processEntityCurrencyCommission(array $entityCurrencyCommissionInfo, array $entitiesInfo): void
    {
        $service = new EntityCurrencyCommissionService();
        $entityService = new EntitiesService();
        $entityCurrencyCommissionInfo = $service->removeEmptyRows($entityCurrencyCommissionInfo);
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);
        $this->saveHeader($entityCurrencyCommissionInfo[0], 'entity_currency_commissions');

        foreach ($entitiesInfo as $entityInfo) {
            try {
                $entity = $entityService->entityExists($entityInfo);
                if($entity){
                    foreach ($entityCurrencyCommissionInfo as &$commissionInfo) {
                        if($commissionInfo['entity_id'] == $entityInfo['id']) {
                            $isStored = $service->entityCurrencyCommissionExists(
                                $entity['id'],
                                $commissionInfo
                            );
                            $commissionInfo['is_stored'] = $isStored ? 'yes' : 'no';
                        }
                    }
                }
                foreach ($entityCurrencyCommissionInfo as &$commissionInfo) {
                    if(!isset($commissionInfo['is_stored'])) {
                        $commissionInfo['is_stored'] = 'no';
                    }
                }
            }catch (\Throwable $e) {
                logger()->error($e->getMessage());
                $this->error($e->getMessage());
            }
        }
        Cache::put('entityCurrencyCommissionInfo', $entityCurrencyCommissionInfo, now()->addDay());
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

    private function showAndProcessSheetsOptions(array $dataSources): void
    {
        $selectedOptions = [];

        $menuBuilder = new CliMenuBuilder;
        $menuBuilder->setTitle('Choose Data to Import (Multiple Selection Allowed)');

        foreach ($dataSources as $name => $data) {
            $menuBuilder->addCheckboxItem($name, function(CliMenu $menu) use (&$selectedOptions, $name) {
                $selectedOptions[] = $name;
            });
        }

        $menuBuilder->addItem('Confirm Selection', function(CliMenu $menu) use (&$selectedOptions, $dataSources) {
            foreach ($selectedOptions as $selected) {
                [$function, $parameters] = $dataSources[$selected];
                $this->processData($function, $parameters);
            }

            $this->newLine();
            $this->output->success('Processing completed');
            $selectedOptions = [];
            $this->processExcel();
        });

        $menuBuilder->disableDefaultItems();
        $menu = $menuBuilder->build();
        $menu->open();
    }


    private function processData($function, array $parameters): void
    {
        call_user_func_array([$this, $function], $parameters);
    }

    private function processExcel(): void
    {
        Excel::store(new FileExport(), 'output.xlsx', 'public');
    }

    private function saveHeader($row, $sheet): void
    {
        $keys = array_keys($row);
        $header = [];
        foreach ($keys as $key) {
            $header[] = Str::ucfirst(Str::replace('_', ' ', $key));
        }
        $header[] = 'Exists';
        Cache::put($sheet . '_header', $header, now()->addDay());
    }
}
