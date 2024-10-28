<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlatformSheet implements FromCollection, WithColumnWidths, WithHeadings
{
    public function __construct(
        readonly private array $headings,
        readonly private array $platforms,
    ){}
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect($this->platforms);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 25,
            'C' => 25,
            'D' => 25,
            'E' => 25,
        ];
    }
    public function headings(): array
    {
        return $this->headings;
    }
}
