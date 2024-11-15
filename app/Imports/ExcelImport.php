<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExcelImport implements WithMultipleSheets
{
//    /**
//    * @param Collection $collection
//    */
//    public function collection(Collection $collection)
//    {
//        //
//    }

    public function sheets(): array
    {
        return [
            0 => new TransactionsImport(),
            1 => new CurrencyImport(),
            2 => new EntitiesImport(),
            3 => new UsersImport(),
            4 => new AccountsImport(),
            5 => new BanksImport(),
            6 => new PlatformsImport(),
            7 => new PaymentsImport(),
            8 => new MasterAgentImport(),
//            9 => new EntityCurrencyCommissionImport(),
        ];
    }
}
