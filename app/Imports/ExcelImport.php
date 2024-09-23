<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\HasReferencesToOtherSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
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
            1 => new EntitiesImport(),
            2 => new UsersImport(),
            3 => new AccountsImport(),
            4 => new BanksImport(),
            5 => new PlatformsImport(),
            6 => new PaymentsImport(),
            7 => new MasterAgentImport(),
        ];
    }
}
