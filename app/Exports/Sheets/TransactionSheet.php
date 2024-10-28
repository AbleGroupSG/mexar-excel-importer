<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionSheet implements FromCollection, WithColumnWidths, WithHeadings, WithTitle
{
    public function __construct(
        readonly private array $header,
        readonly private array $transaction,
    ){}
    public function collection()
    {
        return collect($this->transaction);
    }

    public function columnWidths(): array
    {
        return [
          'A' => 25,
          'B' => 25,
          'C' => 25,
          'D' => 25,
          'E' => 25,
          'F' => 25,
          'G' => 25,
          'H' => 25,
          'I' => 25,
          'J' => 25,
          'K' => 25,
          'L' => 25,
          'M' => 25,
        ];
    }
    public function headings(): array
    {
        return $this->header;
    }

    public function title(): string
    {
        return 'Transactions';
    }
}
