<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersSheet implements FromCollection, WithColumnWidths, WithHeadings, WithTitle
{
    public function __construct(
        readonly private array $headings,
        readonly private array $users,
    ){}
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect($this->users);
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

    public function title(): string
    {
        return 'Users';
    }
}
