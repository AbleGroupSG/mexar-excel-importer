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
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProcessCommand extends Command
{
    const SG_COUNTRY_ID = 220;
    const DEPARTMENT_ID = 1;
    protected $signature = 'process.excel';

    /**
     * @throws \Exception
     */
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

//        $this->processEntities($entitiesInfo->toArray());
//        $this->processUsers($usersInfo->toArray());
        $this->processTransactions($transactionsInfo->toArray(), $payments->toArray());
//        $this->processBank($banks->toArray());
//        $this->processPlatforms($platforms->toArray());
//        $this->processAccounts($accounts->toArray());
//        $this->processMasterAgent($masterAgent->toArray());



    }

    /**
     * @throws \Exception
     */
    public function processEntities(array $entitiesInfo): void
    {
        $service = new EntitiesService();
        $entitiesInfo = $service->removeEmptyRows($entitiesInfo);

        $this->info('Processing entities');
        $progressBar = $this->output->createProgressBar(sizeof($entitiesInfo));
        $progressBar->start();

        foreach ($entitiesInfo as $entityInfo) {
            $service->findOrCreateEntity($entityInfo);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    /**
     * @throws \Exception
     */
    public function processUsers(array $usersInfo): void
    {
        $service = new UserService();
        $usersInfo = $service->removeEmptyRows($usersInfo);

        $this->info('Processing users');
        $progressBar = $this->output->createProgressBar(sizeof($usersInfo));
        $progressBar->start();

        foreach ($usersInfo as $userInfo) {
            $service->createUser($userInfo);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    /**
     * @throws \Exception
     */
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
                throw new \Exception('Entity id is required for transaction');
            }
            $entity = $service->findEntity($transaction[10]);
            if(!$entity) {
                throw new \Exception('Entity id ' . $transaction[10] . ' for transaction not found');
            }
            $newTransaction = $service->createTransaction($transaction, $entity, $payments);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    /**
     * @throws \Exception
     */
    public function processBank(array $banks): void
    {
        $service = new BankService();
        $banks = $service->removeEmptyRows($banks);

        $this->info('Processing banks');
        $progressBar = $this->output->createProgressBar(sizeof($banks));
        $progressBar->start();
        foreach ($banks as $bank) {
            $service->createBank($bank);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    /**
     * @throws \Exception
     */
    public function processPlatforms(array $platformsInfo): void
    {
        $service = new PlatformService();
        $platformsInfo = $service->removeEmptyRows($platformsInfo);

        $this->info('Processing platforms');
        $progressBar = $this->output->createProgressBar(sizeof($platformsInfo));
        $progressBar->start();
        foreach ($platformsInfo as $platformInfo) {
            $service->createPlatform($platformInfo);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }

    /**
     * @throws \Exception
     */
    public function processAccounts(array $accountsInfo): void
    {
        $service = new AccountService();
        $accountsInfo = $service->removeEmptyRows($accountsInfo);

        $this->info('Processing accounts');
        $accounts = $service->prepareAccounts($accountsInfo);
        $progressBar = $this->output->createProgressBar(sizeof($accounts));
        $progressBar->start();

        foreach ($accounts as $accountInfo) {
            $service->createAccount($accountInfo);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();

//        dd($groupedAccounts); // For debugging, you can dump the grouped accounts
    }

    public function processMasterAgent(array $masterAgentInfo): void
    {
        $service = new MasterAgentService();
        $masterAgentInfo = $service->removeEmptyRows($masterAgentInfo);

        $this->info('Processing master agents');
        $masterAgents = $service->prepareMasterAgent($masterAgentInfo);
        $progressBar = $this->output->createProgressBar(sizeof($masterAgents));

        foreach ($masterAgents as $masterAgent) {
            $service->createMasterAgent($masterAgent);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newLine();
    }


}
