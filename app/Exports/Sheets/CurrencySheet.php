<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CurrencySheet implements FromCollection, WithColumnWidths, WithHeadings, WithTitle
{

    public function __construct(
        readonly private array $headings,
        readonly private array $currencies,
    ){}
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect($this->currencies);
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
        ];
    }
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return 'Currencies';
    }
}
