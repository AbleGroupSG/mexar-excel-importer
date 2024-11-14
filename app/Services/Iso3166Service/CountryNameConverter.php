<?php

namespace App\Services\Iso3166Service;

use Illuminate\Support\Facades\Log;
use League\ISO3166\ISO3166;

class CountryNameConverter
{
    public static function convertCountryToAlpha2(string|null $countryName): string|null
    {
        $iso3166 = new ISO3166();

        if(in_array($countryName, array_values(self::countryCodes()))) {
            return array_search($countryName, self::countryCodes());
        }
        if($countryName) {
            try {
                $countryInfo = $iso3166->name($countryName);
                return $countryInfo['alpha2'];
            } catch (\Exception $e) {
                Log::info('The country not found: ' . $countryName);
                return null;
            }
        }
        return null;
    }

    public static function convertAlpha2ToCountryName(string|null $countryCode): string|null
    {
        $iso3166 = new ISO3166();
        if(in_array($countryCode, array_keys(self::countryCodes()))) {
            return self::countryCodes()[$countryCode];
        }

        $countryInfo = $iso3166->alpha2($countryCode);
        return $countryInfo['name'];
    }

    private static function countryCodes():array
    {
        return [
            'BU' => 'MYANMAR',
            'CB' => 'CROATIA',
            'DG' => 'GERMANY',
            'EN' => 'ESTONIA',
            'GC' => 'BRITISH INDIAN OCEAN TERRITORY',
            'GJ' => 'BRITISH PROTECTED PERSON',
            'GO' => 'GEORGIA',
            'KA' => 'CAMBODIA',
            'LH' => 'LITHUANIA',
            'MB' => 'MACEDONIA',
            'NT' => 'NETHERLANDS',
            'RF' => 'RUSSIAN FEDERATION',
            'SW' => 'KITTAN (SAINT KITTS AND NEVIS)',
            'TI' => 'TAJIKISTAN',
            'TP' => 'EAST TIMOR',
            'UK' => 'UNITED KINGDOM',
            'UR' => 'UKRAINE',
            'YM' => 'YEMEN',
            'MJ' => 'MONTENEGRO',
            'KV' => 'KOSOVO',
            'MO' => 'MACAU',
            'PH' => 'PHILIPINES',
        ];
    }
}
