<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Console\Commands\ProcessCommand;
use Tests\TestCase;

class ProcessExcelCommandTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_if_the_command_run_success(): void
    {
        $this->artisan('process.excel')
            ->assertExitCode(0);
    }
}
