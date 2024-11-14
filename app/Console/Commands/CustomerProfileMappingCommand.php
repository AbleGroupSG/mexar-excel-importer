<?php

namespace App\Console\Commands;

use App\Imports\CustomerImport;
use App\Services\BaseService;
use App\Services\Iso3166Service\CountryNameConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class CustomerProfileMappingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:map.customer.profile';

    protected $description = 'Command description';

    public function handle()
    {
        $path = 'customer_profile_2910.xlsx';
        $rawData = Excel::toCollection(new CustomerImport(), $path, 'public')->first();
        $data = $this->removeEmptyRows($rawData->toArray());


        Cache::forget('mapped.customers');
        $progressBar = $this->output->createProgressBar(sizeof($data));
        $progressBar->start();

        $importFileFormat = collect();
        foreach ($data as $row) {
            $contactNos = Str::contains($row['contact_no'], '/') ? explode('/', $row['contact_no']) : [$row['contact_no']];
            $contactTypes = Str::contains($row['contact_type'], '/') ? explode('/', $row['contact_type']) : [$row['contact_type']];

            $lastNonNullableContactType = $contactTypes[0] ?? null;
            $lastNonNullableContactNo = $contactNos[0] ?? null;

            $importFileFormat->push([
                'id'=> $row['id'],
                'entity_type' => $row['entity_type'] ?? 'individual',
                'name'=> $row['name'] ?? null,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'gender'=> $row['gender'] ? $this->parseGender($row['gender']) : null,
                'country'=> $row['country'] ? $this->parseCountry($row['country']) : null,
                'contact_type' => $lastNonNullableContactType,
                'field_1' => $row['country_code'] ?? null,
                'field_2' => $lastNonNullableContactNo ?? null,
                'identity_type' => $row['identity_type'] ?? null,
                'identity_number' => $row['identity_number'] ?? null,
                'identity_expires_at' => $row['identity_expires_at'] ?? null,
                'remark'=> $row['remark'] ?? null,
                'referrer'=> $row['referrer'] ?? null,
            ]);

            for ($i = 1; $i < max(count($contactTypes), count($contactNos)); $i++) {
                $contactType = $contactTypes[$i] ?? $lastNonNullableContactType;
                $contactNo = $contactNos[$i] ?? $lastNonNullableContactNo;

                $lastNonNullableContactType = $contactType ?? $lastNonNullableContactType;
                $lastNonNullableContactNo = $contactNo ?? $lastNonNullableContactNo;

                $importFileFormat->push([
                    'id' => null,
                    'entity_type' => null,
                    'name' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'gender' => null,
                    'country' => null,
                    'contact_type' => $contactType,
                    'field_1' => $row['country_code'] ?? null,
                    'field_2' => $contactNo,
                    'identity_type' => null,
                    'identity_number' => null,
                    'identity_expires_at' => null,
                    'remark' => null,
                    'referrer' => null,
                ]);
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        Cache::put('mapped.customers', $importFileFormat->toArray(), now()->addDay());
    }

    private function parseGender(string $gender):string
    {
        $gender = Str::lower($gender);
        return match($gender) {
            'male', 'm' => 'm',
            'female', 'f' => 'f',
            default => '',
        };
    }

    private function parseCountry(string $country):string
    {
        if(Str::contains($country, '/')) {
            $country = explode('/', $country)[0];
        }

        if(Str::length($country) == 2) {
            return $country;
        }

        if($country == '外国人') return '';

        $alfa = CountryNameConverter::convertCountryToAlpha2($country);
        if(!$alfa) {
            dd($country);
        }
        return $alfa;
    }

    private function removeEmptyRows(array $data):array
    {
        foreach ($data as $key => $row) {
            $isAllEmpty = true;
            foreach ($row as $item) {
                if (!empty($item)) {
                    $isAllEmpty = false;
                }
            }
            if ($isAllEmpty) {
                Arr::forget($data, $key);
            }
        }
        return $data;
    }
}
