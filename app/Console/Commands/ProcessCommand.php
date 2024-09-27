<?php

namespace App\Console\Commands;

use App\Imports\ExcelImport;
use App\Services\AccountService;
use App\Services\BankService;
use App\Services\CurrencyService;
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

class ProcessCommand extends Command
{
    protected $signature = 'process.excel';
    public function handle(): void
    {
        Cache::forget('token');
        $path = 'excel1.xlsx';
        $data = Excel::toCollection(new ExcelImport, $path, 'public');

        $this->selectDepartmentID();
        $this->completeTransactionOption();

        $transactionsInfo = $data[0]->slice(1);
        $currencyInfo = $data[1]->slice(1);
        $entitiesInfo = $data[2]->slice(1);
        $banks = $data[5]->slice(1);
        $usersInfo = $data[3]->slice(1);
        $accounts = $data[4]->slice(1);
        $platforms = $data[6]->slice(1);
        $payments = $data[7]->slice(1);
        $masterAgent = $data[8]->slice(1);

        $dataSources = [
            'Users Info'          => ['processUsers', [$usersInfo->toArray()]],
            'Entities Info'       => ['processEntities', [$entitiesInfo->toArray()]],
            'Banks'               => ['processBank', [$banks->toArray()]],
            'Department Currency' => ['processCurrencies', [$currencyInfo->toArray()]],
            'Master Agent'        => ['processMasterAgent', [$masterAgent->toArray(), $entitiesInfo->toArray()]],
            'Platforms'           => ['processPlatforms', [$platforms->toArray()]],
            'Accounts'            => ['processAccounts', [$accounts->toArray()]],
            'Transactions Info'   => ['processTransactions', [$transactionsInfo->toArray(), $payments->toArray(), $entitiesInfo->toArray()]],
        ];

        $this->showAndProcessSheetsOptions($dataSources);

    }

    private function  selectDepartmentID(): void
    {
        $departments = config('mexar.departments');
        $menuBuilder = (new CliMenuBuilder)
            ->setTitle('Select department ID:');

        foreach ($departments as $departmentId) {
            $menuBuilder->addRadioItem('Department '. $departmentId, function(CliMenu $menu) use ($departmentId) {
                Cache::put('departmentId', (int) $departmentId, now()->addDay());
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

    public function processCurrencies(array $currenciesInfo): void
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


    public function processEntities(array $entitiesInfo): void
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing entities');
        $progressBar = $this->output->createProgressBar(sizeof($entitiesInfo));
        $progressBar->start();

        foreach ($entitiesInfo as $entityInfo) {
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
    }


    public function processUsers(array $usersInfo): void
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);

        $this->info('Processing users');
        $progressBar = $this->output->createProgressBar(sizeof($usersInfo));
        $progressBar->start();

        foreach ($usersInfo as $userInfo) {
            try {
                $service->createUser($userInfo);
            }catch (\Throwable $e) {
                logger()->error('Error processing user: ' . $e->getMessage(), $userInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    public function processTransactions(array $transactionsInfo, array $payments, array $entitiesInfo): void
    {
        $service = new TransactionService();
        $transactionsInfo = $service->removeEmptyRows($transactionsInfo);
        $payments = $service->removeEmptyRows($payments);
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing transactions');
        $progressBar = $this->output->createProgressBar(sizeof($transactionsInfo));
        $progressBar->start();
        foreach ($transactionsInfo as $transaction) {
            if (empty($transaction[10])) {
                logger()->error('Entity id is empty for transaction', $transaction);
            }
            try {
                $entityId = $service->getEntityIdFromEntitiesSheet($transaction[10], $entitiesInfo);

                if(!$entityId) {
                    logger()->error('Entity not found for transaction', $transaction);
                    continue;
                }
                $res = $service->createTransaction($transaction, $entityId, $payments);

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

    public function processBank(array $banks): void
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


    public function processPlatforms(array $platformsInfo): void
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

    public function processAccounts(array $accountsInfo): void
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

    public function processMasterAgent(array $masterAgentInfo, array $entitiesInfo): void
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
}
