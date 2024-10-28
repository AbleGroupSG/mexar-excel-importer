<?php

namespace App\Console\Commands;

use App\Exports\FileExport;
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
                $entitiesInfo->toArray()
            ]],
//            'Entity Currency Commission' => ['processEntityCurrencyCommission', [
//                $entityCurrencyCommissionInfo->toArray(),
//                $entitiesInfo->toArray()
//            ]],
        ];

        $this->showAndProcessSheetsOptions($dataSources);
    }

    private function processUsers(array $usersInfo): void
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);
        $this->saveHeader($usersInfo[0], 'users');
        foreach ($usersInfo as &$userInfo) {
            $isStored = $service->userExists($userInfo);
            $userInfo['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('usersInfo', $usersInfo, now()->addDay());
    }
    private function processEntities(array $entitiesInfo): void
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);
        $this->saveHeader($entitiesInfo[0], 'entities');
        foreach ($entitiesInfo as &$entityInfo) {
            $isStored = $service->entityExists($entityInfo);
            $entityInfo['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('entitiesInfo', $entitiesInfo, now()->addDay());
    }
    private function processBanks(array $banksInfo): void
    {
        $service = new BankService();
        $banksInfo = $service->removeEmptyRows($banksInfo);
        $this->saveHeader($banksInfo[0], 'banks');
        foreach ($banksInfo as &$bank) {
            $isStored = $service->bankExists($bank);
            $bank['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('banksInfo', $banksInfo, now()->addDay());
    }
    private function processCurrencies(array $currenciesInfo): void
    {
        $service = new CurrencyService();
        $currenciesInfo = $service->removeEmptyRows($currenciesInfo);
        $this->saveHeader($currenciesInfo[0], 'currencies');
        foreach ($currenciesInfo as &$currencyInfo) {
            $isStored = $service->currencyExists($currencyInfo);
            $currencyInfo['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('currenciesInfo', $currenciesInfo, now()->addDay());
    }
    private function processMasterAgent(array $masterAgentInfo): void
    {
        $service = new MasterAgentService();
        $masterAgentInfo = $service->removeEmptyRows($masterAgentInfo);
        $this->saveHeader($masterAgentInfo[0], 'master_agents');
        foreach ($masterAgentInfo as &$agentInfo) {
            $isStored = $service->masterAgentExists($agentInfo);
            $agentInfo['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('masterAgentInfo', $masterAgentInfo, now()->addDay());
    }
    private function processPlatforms(array $platformsInfo): void
    {
        $service = new PlatformService();
        $platformsInfo = $service->removeEmptyRows($platformsInfo);
        $this->saveHeader($platformsInfo[0], 'platforms');
        foreach ($platformsInfo as &$platformInfo) {
            $isStored = $service->platformExists($platformInfo);
            $platformInfo['is_stored'] = $isStored ? 'yes': 'no';
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
            $isStored = $service->accountExists($accountInfo);
            $accountInfo['is_stored'] = $isStored ? 'yes': 'no';
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
            $isStored = $service->transactionExists($transactionInfo, $entitiesInfo);
            $transactionInfo['is_stored'] = $isStored ? 'yes': 'no';
        }
        Cache::put('transactionsInfo', $transactionsInfo, now()->addDay());
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
    //            $this->info('Processing completed');
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
