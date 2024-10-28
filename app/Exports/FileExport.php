<?php

namespace App\Exports;

use App\Exports\Sheets\AccountsSheet;
use App\Exports\Sheets\BankSheet;
use App\Exports\Sheets\CurrencySheet;
use App\Exports\Sheets\EntitiesSheet;
use App\Exports\Sheets\EntityCurrencyCommissionSheet;
use App\Exports\Sheets\MasterAgentSheet;
use App\Exports\Sheets\PaymentsSheet;
use App\Exports\Sheets\PlatformSheet;
use App\Exports\Sheets\TransactionSheet;
use App\Exports\Sheets\UsersSheet;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FileExport implements WithMultipleSheets
{
    public function __construct()
    {
    }

    public function sheets(): array
    {
        return [
            new TransactionSheet(
                Cache::get('transactions_header', []),
                Cache::get('usersInfo', []),
            ),
            new CurrencySheet(
                Cache::get('currencies_header', []),
                Cache::get('entitiesInfo', []),
            ),
            new EntitiesSheet(
                Cache::get('entities_header', []),
                Cache::get('banksInfo', []),
            ),
            new BankSheet(
                Cache::get('currenciesInfo', []),
                Cache::get('banks_header', [])
            ),
            new UsersSheet(
                Cache::get('users_header', []),
                Cache::get('masterAgentInfo', []),
            ),
            new AccountsSheet(
                Cache::get('accounts_header', []),
                Cache::get('platformsInfo', []),
            ),
            new PlatformSheet(
                Cache::get('platforms_header', []),
                Cache::get('accountsInfo', []),
            ),
            new PaymentsSheet(
                Cache::get('payments_header', []),
                Cache::get('transactionsInfo', []),
            ),
            new MasterAgentSheet(
                Cache::get('master_agents_header', []),
                Cache::get('paymentsInfo', []),
            ),
            new EntityCurrencyCommissionSheet(
                Cache::get('entity_currency_commissions_header', []),
                Cache::get('entityCurrencyCommissionInfo', []),
            ),
        ];
    }
}
