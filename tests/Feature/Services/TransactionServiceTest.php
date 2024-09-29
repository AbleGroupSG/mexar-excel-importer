<?php

namespace Tests\Feature\Services;

use App\Console\Commands\ProcessCommand;
use App\Imports\ExcelImport;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $path = 'excel1.xlsx';
        $data = Excel::toCollection(new ExcelImport(), $path, 'public');

        $this->assertNotEmpty($data);

        $tData = $data[0]->toArray();
        $pData = $data[7]->toArray();
        $eData = $data[2]->toArray();
        
        $cmd = new ProcessCommand();

        $cmd->processTransactions($tData, $pData, $eData);
    }
}
