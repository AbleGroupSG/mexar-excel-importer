<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\HasReferencesToOtherSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class TransactionsImport implements ToCollection, WithCalculatedFormulas, HasReferencesToOtherSheets
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
    }
}
