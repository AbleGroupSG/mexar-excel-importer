<?php

namespace App\Console\Commands;

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
use Maatwebsite\Excel\Facades\Excel;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;

class ProcessCommand extends Command
{
    protected $signature = 'process.excel';
    public function handle(): void
    {
        $path = 'output.xlsx';
        $data = Excel::toCollection(new ExcelImport, $path, 'public');
        $this->info('Loading...');

        $this->selectDepartmentID();
        $this->completeTransactionOption();

        $transactionsInfo = $data[0]->toArray();
        $currencyInfo = $data[1]->toArray();
        $entitiesInfo = $data[2]->toArray();
        $banks = $data[5]->toArray();
        $usersInfo = $data[3]->toArray();
        $accounts = $data[4]->toArray();
        $platforms = $data[6]->toArray();
        $payments = $data[7]->toArray();
        $masterAgent = $data[8]->toArray();
//        $entityCurrencyCommissionInfo = $data[9]->toArray();

        if(Cache::has('mapped.customers')) {
            $entitiesInfo = Cache::get('mapped.customers', []);
        }

        $dataSources = [
            'Users Info'                 => ['processUsers', [$usersInfo]],
            'Entities Info'              => ['processEntities', [$entitiesInfo]],
            'Banks'                      => ['processBank', [$banks]],
            'Department Currency'        => ['processCurrencies', [$currencyInfo]],
            'Master Agent'               => ['processMasterAgent', [$masterAgent, $entitiesInfo]],
            'Platforms'                  => ['processPlatforms', [$platforms]],
            'Accounts'                   => ['processAccounts', [$accounts]],
            'Transactions Info'          => ['processTransactions', [
                $transactionsInfo,
                $payments,
                $entitiesInfo,
                $masterAgent
            ]],
//            'Entity Currency Commission' => ['processEntityCurrencyCommission', [
//                $entityCurrencyCommissionInfo,
//                $entitiesInfo
//            ]],
        ];

        $this->showAndProcessSheetsOptions($dataSources);

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

    private function  completeTransactionOption(): void
    {
        $menuBuilder = (new CliMenuBuilder)
            ->setTitle('Complete transaction after creating');

        $menuBuilder->addRadioItem('Yes', function(CliMenu $menu) {
            Cache::put('completeTransaction', true, now()->addDay());
            $menu->close();
        });
        $menuBuilder->addRadioItem('No', function(CliMenu $menu) {
            Cache::put('completeTransaction', false, now()->addDay());
            $menu->close();
        });

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
        });

        $menuBuilder->disableDefaultItems();

        $menu = $menuBuilder->build();
        $menu->open();
    }


    private function processData($function, array $parameters): void
    {
        call_user_func_array([$this, $function], $parameters);
    }

    private function processCurrencies(array $currenciesInfo): void
    {
        $service = new CurrencyService();
        $currenciesInfo = $service->removeEmptyRows($currenciesInfo);

        $this->info('Processing currencies');
        $progressBar = $this->output->createProgressBar(sizeof($currenciesInfo));
        $progressBar->start();

        foreach ($currenciesInfo as $currencyInfo) {
            try {
                $departmentId = Cache::get('departmentId', 1);
                $service->createCurrency($currencyInfo, $departmentId);
            } catch (\Throwable $e) {
                logger()->error('Error processing currency: ' . $e->getMessage(), $currencyInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }


    private function processEntities(array $entitiesInfo): void
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing entities');
        $progressBar = $this->output->createProgressBar(sizeof($entitiesInfo));
        $progressBar->start();

        $mappedEntities = $service->mappedEntities($entitiesInfo);
        foreach ($mappedEntities as $entityInfo) {
            try {
                $service->findOrCreateEntity($entityInfo);
            }catch (\Throwable $e) {
                logger()->error('Error processing entity: ' . $e->getMessage(), $entityInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();

        $this->info("Processing entity's referrer");
        $progressBar = $this->output->createProgressBar(sizeof($entitiesInfo));
        $progressBar->start();
        foreach ($entitiesInfo as $entityInfo) {
            try {
                $service->attachReferEntity($entityInfo, $entitiesInfo);
            }catch (\Throwable $e) {
                logger()->error('Error processing entity: ' . $e->getMessage(), $entityInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }


    private function processUsers(array $usersInfo): void
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);

        $this->info('Processing users');
        $progressBar = $this->output->createProgressBar(sizeof($usersInfo));
        $progressBar->start();

        foreach ($usersInfo as $userInfo) {
            try {
                $userId = $service->createUser($userInfo);
                if($userId) {
                    $departmentId = Cache::get('departmentId', 1);
                    $service->addUser2Department($userId, $departmentId);
                }
            }catch (\Throwable $e) {
                logger()->error('Error processing user: ' . $e->getMessage(), $userInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    private function processTransactions(array $transactionsInfo, array $payments, array $entitiesInfo, array $masterAgents): void
    {
        $service = new TransactionService();
        $transactionsInfo = $service->removeEmptyRows($transactionsInfo);
        $payments = $service->removeEmptyRows($payments);
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing transactions');
        $progressBar = $this->output->createProgressBar(sizeof($transactionsInfo));
        $progressBar->start();
        foreach ($transactionsInfo as $transaction) {
            if (empty($transaction['entity_id'])) {
                logger()->error('Entity id is empty for transaction', $transaction);
            }
            try {
                $entityId = $service->getEntityIdFromEntitiesSheet($transaction['entity_id'], $entitiesInfo);

                if(!$entityId) {
                    logger()->error('Entity not found for transaction', $transaction);
                    continue;
                }
                $res = $service->createTransaction($transaction, $entityId, $payments, $masterAgents);

                if(Cache::get('completeTransaction', false)) {
                    if(!isset($res['transaction']['id'])) {
                        throw new \Exception('Cannot complete transaction, transaction id is empty');
                    }
                    $service->completeTransaction($res['transaction']['id']);
                }

            } catch (\Throwable $e) {
                logger()->error('Error processing transaction: ' . $e->getMessage(), $transaction);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    private function processBank(array $banks): void
    {
        $service = new BankService();
        $banks = $service->removeEmptyRows($banks);

        $this->info('Processing banks');
        $progressBar = $this->output->createProgressBar(sizeof($banks));
        $progressBar->start();
        foreach ($banks as $bank) {
            try {
                $service->createBank($bank);
            }catch (\Throwable $e) {
                logger()->error('Error processing bank: ' . $e->getMessage(), $bank);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }


    private function processPlatforms(array $platformsInfo): void
    {
        $service = new PlatformService();
        $platformsInfo = $service->removeEmptyRows($platformsInfo);

        $this->info('Processing platforms');
        $progressBar = $this->output->createProgressBar(sizeof($platformsInfo));
        $progressBar->start();
        foreach ($platformsInfo as $platformInfo) {
            try {
                $service->createPlatform($platformInfo);
            }catch (\Throwable $e) {
                logger()->error('Error processing platform: ' . $e->getMessage(), $platformInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    private function processAccounts(array $accountsInfo): void
    {
        $service = new AccountService();
        $accountsInfo = $service->removeEmptyRows($accountsInfo);

        $this->info('Processing accounts');
        try {
            $accounts = $service->prepareAccounts($accountsInfo);
        } catch (\Throwable $e) {
            logger()->error('Error processing accounts: ' . $e->getMessage(), $accountsInfo);
            return;
        }
        $progressBar = $this->output->createProgressBar(sizeof($accounts));
        $progressBar->start();

        foreach ($accounts as $accountInfo) {
            try {
                $service->createAccount($accountInfo);

            } catch (\Throwable $e) {
                logger()->error('Error processing account: ' . $e->getMessage(), $accountInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();

    }

    private function processMasterAgent(array $masterAgentInfo, array $entitiesInfo): void
    {
        $service = new MasterAgentService();
        $masterAgentInfo = $service->removeEmptyRows($masterAgentInfo);
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing master agents');
        try {
            $masterAgents = $service->prepareMasterAgent($masterAgentInfo, $entitiesInfo);
        } catch (\Throwable $e) {
            logger()->error('Error processing master agents: ' . $e->getMessage(), $masterAgentInfo);
            return;
        }
        $progressBar = $this->output->createProgressBar(sizeof($masterAgents));

        foreach ($masterAgents as $masterAgent) {
            try {
                $service->createMasterAgent($masterAgent);
            } catch (\Throwable $e) {
                logger()->error('Error processing master agent: ' . $e->getMessage(), $masterAgent);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    private function processEntityCurrencyCommission(array $entityCurrencyCommissionInfo, array $entitiesInfo): void
    {
        $service = new EntityCurrencyCommissionService();
        $entityService = new EntitiesService();
        $entityCurrencyCommissionInfo = $service->removeEmptyRows($entityCurrencyCommissionInfo);
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);
        foreach ($entityCurrencyCommissionInfo as $commission) {
            $entityInfo = array_filter($entitiesInfo, function($entity) use ($commission) {
                return $entity['id'] === $commission['entity_id'];
            })[0] ?? null;

            if (!$entityInfo) {
                logger()->error('Entity not found for commission', $commission);
                continue;
            }

            try {
                $entity = $entityService->findOrCreateEntity($entityInfo);
                if (!$entity) {
                    logger()->error('Error processing entity for commission', $entityInfo);
                    continue;
                }
                $service->createOrUpdateCommission($commission, $entity);

            }catch (\Throwable $e) {
                logger()->error('Error processing entity: ' . $e->getMessage(), $entityInfo);
                continue;
            }
        }
    }


    private function fetchAllUsers(): void
    {
        $users = (new UserService())->fetchAllUsers();
        if(count($users) === 0) {
            $this->error('No users found');
            return;
        }
        Cache::put('existedUsers', $users, now()->addDay());
    }

    private function fetchEntities(): void
    {
        $entities = (new EntitiesService())->fetchAllEntities();
        if(count($entities) === 0) {
            $this->error('No entities found');
            return;
        }
        Cache::put('existedEntities', $entities, now()->addDay());
    }

    private function fetchBanks(): void
    {
        $banks = (new BankService())->fetchAllBanks();
        if(count($banks) === 0) {
            $this->error('No banks found');
            return;
        }
        Cache::put('existedBanks', $banks, now()->addDay());
    }

    private function fetchCurrencies(): void
    {
        $currencies = (new CurrencyService())->fetchAllCurrencies();
        if(count($currencies) === 0) {
            $this->error('No currencies found');
            return;
        }
        Cache::put('existedCurrencies', $currencies, now()->addDay());
    }

    private function fetchMasterAgents(): void
    {
        $masterAgents = (new MasterAgentService())->fetchAllMasterAgents();
        if(count($masterAgents) === 0) {
            $this->error('No master agents found');
            return;
        }
        Cache::put('existedMasterAgents', $masterAgents, now()->addDay());
    }

    private function fetchPlatforms(): void
    {
        $platforms = (new PlatformService())->fetchAllPlatforms();
        if(count($platforms) === 0) {
            $this->error('No platforms found');
            return;
        }
        Cache::put('existedPlatforms', $platforms, now()->addDay());
    }

    private function fetchAccounts(): void
    {
        $accounts = (new AccountService())->fetchAllAccounts();
        if(count($accounts) === 0) {
            $this->error('No accounts found');
            return;
        }
        Cache::put('existedAccounts', $accounts, now()->addDay());
    }

    private function fetchTransactions(): void
    {
        $existedAccounts = Cache::get('existedAccounts', []);
        $transactions = [];
        foreach ($existedAccounts as $account) {
            $transactions[] = (new TransactionService())->fetchAllTransactions($account['id']);
        }
        Cache::put('existedTransactions', $transactions, now()->addDay());
    }

    private function fetchEntityCurrencyCommission(): void
    {
        $existedEntities = Cache::get('existedEntities', []);
        $commissions = [];
        foreach ($existedEntities as $entity) {
            $commissions[] = (new EntityCurrencyCommissionService())->fetchEntityCurrencyCommission($entity['id']);
        }
        Cache::put('existedEntityCurrencyCommission', $commissions, now()->addDay());
    }
}
