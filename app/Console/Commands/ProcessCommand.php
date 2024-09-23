<?php

namespace App\Console\Commands;

use App\Imports\ExcelImport;
use App\Services\AccountService;
use App\Services\BankService;
use App\Services\EntitiesService;
use App\Services\MasterAgentService;
use App\Services\PlatformService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ProcessCommand extends Command
{
    protected $signature = 'process.excel';
    public function handle(): void
    {

        Cache::forget('token');
        $path = 'excel1.xlsx';
        $data = Excel::toCollection(new ExcelImport, $path, 'public');

        $transactionsInfo = $data[0]->slice(1);
        $entitiesInfo = $data[1]->slice(1);
        $usersInfo = $data[2]->slice(1);
        $accounts = $data[3]->slice(1);
        $banks = $data[4]->slice(1);
        $platforms = $data[5]->slice(1);
        $payments = $data[6]->slice(1);
        $masterAgent = $data[7]->slice(1);

        $this->processEntities($entitiesInfo->toArray());
        $this->processUsers($usersInfo->toArray());
        $this->processMasterAgent($masterAgent->toArray());
        $this->processTransactions($transactionsInfo->toArray(), $payments->toArray());
        $this->processBank($banks->toArray());
        $this->processPlatforms($platforms->toArray());
        $this->processAccounts($accounts->toArray());

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
            }catch (\Exception $e) {
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
            }catch (\Exception $e) {
                logger()->error('Error processing user: ' . $e->getMessage(), $userInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    public function processTransactions(array $transactionsInfo, array $payments): void
    {
        $service = new TransactionService();
        $transactionsInfo = $service->removeEmptyRows($transactionsInfo);
        $payments = $service->removeEmptyRows($payments);

        $this->info('Processing transactions');
        $progressBar = $this->output->createProgressBar(sizeof($transactionsInfo));
        $progressBar->start();
        foreach ($transactionsInfo as $transaction) {
            if (empty($transaction[10])) {
                logger()->error('Entity id is empty for transaction', $transaction);
            }
            try {
                $entity = $service->findEntity($transaction[10]);
                if(!$entity) {
                    logger()->error('Entity not found for transaction', $transaction);
                    continue;
                }
                $service->createTransaction($transaction, $entity, $payments);
            }catch (\Exception $e) {
                logger()->error('Error processing transaction: ' . $e->getMessage(), $transaction);
                continue;
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
            }catch (\Exception $e) {
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
            }catch (\Exception $e) {
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
        }catch (\Exception $e) {
            logger()->error('Error processing accounts: ' . $e->getMessage(), $accountsInfo);
            return;
        }
        $progressBar = $this->output->createProgressBar(sizeof($accounts));
        $progressBar->start();

        foreach ($accounts as $accountInfo) {
            try {
                $service->createAccount($accountInfo);
            }catch (\Exception $e) {
                logger()->error('Error processing account: ' . $e->getMessage(), $accountInfo);
                continue;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();

    }

    public function processMasterAgent(array $masterAgentInfo): void
    {
        $service = new MasterAgentService();
        $masterAgentInfo = $service->removeEmptyRows($masterAgentInfo);

        $this->info('Processing master agents');
        try {
            $masterAgents = $service->prepareMasterAgent($masterAgentInfo);
        }catch (\Exception $e) {
            logger()->error('Error processing master agents: ' . $e->getMessage(), $masterAgentInfo);
            return;
        } catch (\Throwable $e) {
            logger()->error('Error processing master agents: ' . $e->getMessage(), $masterAgentInfo);
            return;
        }
        $progressBar = $this->output->createProgressBar(sizeof($masterAgents));

        foreach ($masterAgents as $masterAgent) {
            try {
                $service->createMasterAgent($masterAgent);
            }catch (\Exception $e) {
                logger()->error('Error processing master agent: ' . $e->getMessage(), $masterAgent);
                continue;
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
