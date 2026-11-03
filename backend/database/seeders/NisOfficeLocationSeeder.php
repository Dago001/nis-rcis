<?php

namespace Database\Seeders;

use App\Models\NisCommand;
use App\Models\NisOfficeLocation;
use App\Models\NisZone;
use Illuminate\Database\Seeder;

class NisOfficeLocationSeeder extends Seeder
{
    public function run(): void
    {
        $zonesByCode = NisZone::pluck('id', 'code')->toArray();
        $commandsByState = NisCommand::pluck('id', 'state')->toArray();

        $locations = [
            // NIS Service Headquarters
            [
                'code' => 'NIS_HQ',
                'name' => 'NIS Service Headquarters, Abuja',
                'zone_code' => null,
                'state' => 'Abuja (FCT)',
                'address' => 'Nnamdi Azikiwe Airport Road, Sauka, Abuja, FCT',
                'latitude' => 8.9760000,
                'longitude' => 7.4040000,
                'radius_meters' => 1000,
            ],
            // Zone A - Lagos / Ogun
            [
                'code' => 'NIS_LOC_LAGOS',
                'name' => 'Lagos State Command',
                'zone_code' => 'ZONE_A',
                'state' => 'Lagos',
                'address' => 'Alagbon Close, Ikoyi, Lagos',
                'latitude' => 6.4475000,
                'longitude' => 3.4285000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_MMIA',
                'name' => 'Murtala Muhammed Airport Command, Lagos',
                'zone_code' => 'ZONE_A',
                'state' => 'Lagos',
                'address' => 'Murtala Muhammed International Airport, Ikeja, Lagos',
                'latitude' => 6.5774000,
                'longitude' => 3.3212000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_OGUN',
                'name' => 'Ogun State Command',
                'zone_code' => 'ZONE_A',
                'state' => 'Ogun',
                'address' => 'Old Party Secretariat, Kobape Road, Abeokuta, Ogun State',
                'latitude' => 7.1475000,
                'longitude' => 3.3619000,
                'radius_meters' => 800,
            ],
            // Zone B - Kaduna / Kano / Katsina / Jigawa
            [
                'code' => 'NIS_LOC_KADUNA',
                'name' => 'Kaduna State Command',
                'zone_code' => 'ZONE_B',
                'state' => 'Kaduna',
                'address' => 'Independence Way, Kaduna, Kaduna State',
                'latitude' => 10.5105000,
                'longitude' => 7.4165000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_KANO',
                'name' => 'Kano State Command',
                'zone_code' => 'ZONE_B',
                'state' => 'Kano',
                'address' => 'Farm Centre, Airport Road, Kano, Kano State',
                'latitude' => 12.0022000,
                'longitude' => 8.5919000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_KATSINA',
                'name' => 'Katsina State Command',
                'zone_code' => 'ZONE_B',
                'state' => 'Katsina',
                'address' => 'Hassan Usman Katsina Road, Katsina, Katsina State',
                'latitude' => 12.9908000,
                'longitude' => 7.6018000,
                'radius_meters' => 800,
            ],
            // Zone C - Bauchi / Borno / Adamawa / Gombe / Taraba / Yobe
            [
                'code' => 'NIS_LOC_BAUCHI',
                'name' => 'Bauchi State Command',
                'zone_code' => 'ZONE_C',
                'state' => 'Bauchi',
                'address' => 'Federal Secretariat Complex, Bauchi, Bauchi State',
                'latitude' => 10.3158000,
                'longitude' => 9.8442000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_BORNO',
                'name' => 'Borno State Command',
                'zone_code' => 'ZONE_C',
                'state' => 'Borno',
                'address' => 'Kano Road, Maiduguri, Borno State',
                'latitude' => 11.8333000,
                'longitude' => 13.1500000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_ADAMAWA',
                'name' => 'Adamawa State Command',
                'zone_code' => 'ZONE_C',
                'state' => 'Adamawa',
                'address' => 'Kashim Ibrahim Way, Jimeta-Yola, Adamawa State',
                'latitude' => 9.2094000,
                'longitude' => 12.4818000,
                'radius_meters' => 800,
            ],
            // Zone D - Minna / Kwara / Kogi / Nasarawa / Plateau / FCT
            [
                'code' => 'NIS_LOC_NIGER',
                'name' => 'Niger State Command',
                'zone_code' => 'ZONE_D',
                'state' => 'Niger',
                'address' => 'David Mark Road, Minna, Niger State',
                'latitude' => 9.6139000,
                'longitude' => 6.5569000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_FCT',
                'name' => 'FCT Command, Abuja',
                'zone_code' => 'ZONE_D',
                'state' => 'Federal Capital Territory',
                'address' => 'Gwagwalada Area Command, Abuja, FCT',
                'latitude' => 9.0578500,
                'longitude' => 7.4950800,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_KWARA',
                'name' => 'Kwara State Command',
                'zone_code' => 'ZONE_D',
                'state' => 'Kwara',
                'address' => 'Federal Secretariat Complex, Fate Road, Ilorin, Kwara State',
                'latitude' => 8.4966000,
                'longitude' => 4.5421000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_PLATEAU',
                'name' => 'Plateau State Command',
                'zone_code' => 'ZONE_D',
                'state' => 'Plateau',
                'address' => 'Federal Secretariat, Tudun Wada, Jos, Plateau State',
                'latitude' => 9.8965000,
                'longitude' => 8.8583000,
                'radius_meters' => 800,
            ],
            // Zone E - Owerri / Abia / Enugu / Ebonyi / Anambra
            [
                'code' => 'NIS_LOC_IMO',
                'name' => 'Imo State Command',
                'zone_code' => 'ZONE_E',
                'state' => 'Imo',
                'address' => 'Federal Secretariat Complex, Port Harcourt Road, Owerri, Imo State',
                'latitude' => 5.4836000,
                'longitude' => 7.0333000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_ENUGU',
                'name' => 'Enugu State Command',
                'zone_code' => 'ZONE_E',
                'state' => 'Enugu',
                'address' => 'Federal Secretariat, Independence Layout, Enugu, Enugu State',
                'latitude' => 6.4584000,
                'longitude' => 7.5464000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_ANAMBRA',
                'name' => 'Anambra State Command',
                'zone_code' => 'ZONE_E',
                'state' => 'Anambra',
                'address' => 'Enugu-Onitsha Expressway, Awka, Anambra State',
                'latitude' => 6.2209000,
                'longitude' => 7.0718000,
                'radius_meters' => 800,
            ],
            // Zone F - Ibadan / Osun / Ondo / Ekiti
            [
                'code' => 'NIS_LOC_OYO',
                'name' => 'Oyo State Command',
                'zone_code' => 'ZONE_F',
                'state' => 'Oyo',
                'address' => 'Agodi Post Office Road, Agodi, Ibadan, Oyo State',
                'latitude' => 7.4019000,
                'longitude' => 3.9173000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_OSUN',
                'name' => 'Osun State Command',
                'zone_code' => 'ZONE_F',
                'state' => 'Osun',
                'address' => 'Gbongan-Ibadan Road, Osogbo, Osun State',
                'latitude' => 7.7827000,
                'longitude' => 4.5418000,
                'radius_meters' => 800,
            ],
            // Zone G - Benin / Delta / Bayelsa
            [
                'code' => 'NIS_LOC_EDO',
                'name' => 'Edo State Command',
                'zone_code' => 'ZONE_G',
                'state' => 'Edo',
                'address' => 'Ikpoba Hill, Benin City, Edo State',
                'latitude' => 6.3350000,
                'longitude' => 5.6037000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_DELTA',
                'name' => 'Delta State Command',
                'zone_code' => 'ZONE_G',
                'state' => 'Delta',
                'address' => 'Federal Secretariat Complex, Asaba, Delta State',
                'latitude' => 6.1980000,
                'longitude' => 6.7260000,
                'radius_meters' => 800,
            ],
            // Zone H - Makurdi / Rivers / Cross River / Akwa Ibom
            [
                'code' => 'NIS_LOC_BENUE',
                'name' => 'Benue State Command',
                'zone_code' => 'ZONE_H',
                'state' => 'Benue',
                'address' => 'Otukpo Road, Makurdi, Benue State',
                'latitude' => 7.7322000,
                'longitude' => 8.5391000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_RIVERS',
                'name' => 'Rivers State Command',
                'zone_code' => 'ZONE_H',
                'state' => 'Rivers',
                'address' => 'Federal Secretariat Complex, Aba Road, Port Harcourt, Rivers State',
                'latitude' => 4.8156000,
                'longitude' => 7.0498000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_CROSS_RIVER',
                'name' => 'Cross River State Command',
                'zone_code' => 'ZONE_H',
                'state' => 'Cross River',
                'address' => 'Murtala Muhammed Highway, Calabar, Cross River State',
                'latitude' => 4.9589000,
                'longitude' => 8.3269000,
                'radius_meters' => 800,
            ],
            [
                'code' => 'NIS_LOC_AKWA_IBOM',
                'name' => 'Akwa Ibom State Command',
                'zone_code' => 'ZONE_H',
                'state' => 'Akwa Ibom',
                'address' => 'Federal Secretariat Complex, Abak Road, Uyo, Akwa Ibom State',
                'latitude' => 5.0377000,
                'longitude' => 7.9128000,
                'radius_meters' => 800,
            ],
        ];

        foreach ($locations as $loc) {
            $zoneId = ! empty($loc['zone_code']) && isset($zonesByCode[$loc['zone_code']])
                ? $zonesByCode[$loc['zone_code']]
                : null;

            $commandId = ! empty($loc['state']) && isset($commandsByState[$loc['state']])
                ? $commandsByState[$loc['state']]
                : null;

            NisOfficeLocation::updateOrCreate(
                ['code' => $loc['code']],
                [
                    'name' => $loc['name'],
                    'zone_id' => $zoneId,
                    'command_id' => $commandId,
                    'state' => $loc['state'],
                    'address' => $loc['address'],
                    'latitude' => $loc['latitude'],
                    'longitude' => $loc['longitude'],
                    'radius_meters' => $loc['radius_meters'],
                    'is_active' => true,
                    'enforce_biometrics' => true,
                    'enforce_card_receipt' => true,
                    'enforce_card_issuance' => true,
                ]
            );
        }
    }
}
