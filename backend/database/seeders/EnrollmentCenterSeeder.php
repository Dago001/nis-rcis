<?php

namespace Database\Seeders;

use App\Models\EnrollmentCenter;
use App\Models\NisCommand;
use App\Models\NisZone;
use Illuminate\Database\Seeder;

class EnrollmentCenterSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSlots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00'];

        // 1. Authoritative Zones from official NIS document
        $zonesData = [
            [
                'code' => 'ZONE_A',
                'name' => 'Zone A (Lagos)',
                'headquarters' => 'Lagos',
            ],
            [
                'code' => 'ZONE_B',
                'name' => 'Zone B (Kaduna)',
                'headquarters' => 'Kaduna',
            ],
            [
                'code' => 'ZONE_C',
                'name' => 'Zone C (Bauchi)',
                'headquarters' => 'Bauchi',
            ],
            [
                'code' => 'ZONE_D',
                'name' => 'Zone D (Minna)',
                'headquarters' => 'Minna',
            ],
            [
                'code' => 'ZONE_E',
                'name' => 'Zone E (Owerri)',
                'headquarters' => 'Owerri',
            ],
            [
                'code' => 'ZONE_F',
                'name' => 'Zone F (Ibadan)',
                'headquarters' => 'Ibadan',
            ],
            [
                'code' => 'ZONE_G',
                'name' => 'Zone G (Benin)',
                'headquarters' => 'Benin',
            ],
            [
                'code' => 'ZONE_H',
                'name' => 'Zone H (Makurdi)',
                'headquarters' => 'Makurdi',
            ],
        ];

        $zones = [];
        foreach ($zonesData as $zd) {
            $zones[$zd['code']] = NisZone::updateOrCreate(
                ['code' => $zd['code']],
                ['name' => $zd['name'], 'headquarters' => $zd['headquarters'], 'is_active' => true]
            );
        }

        // 2. Authoritative Commands from official NIS document
        $commandsData = [
            // ZONE A (LAGOS)
            [
                'zone_code' => 'ZONE_A',
                'code' => 'LAG-SC',
                'name' => 'Lagos State Command',
                'state' => 'Lagos',
                'location' => 'Alagbon Close, Ikoyi, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'OGN-SC',
                'name' => 'Ogun State Command',
                'state' => 'Ogun',
                'location' => 'Abeokuta, Ogun State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'LABPC',
                'name' => 'Lagos Border Patrol Command (LABPC)',
                'state' => 'Lagos',
                'location' => 'Seme / Badagry Border Axis, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'LASPMC',
                'name' => 'Lagos Seaport and Marine Command (LASPMC)',
                'state' => 'Lagos',
                'location' => 'Apapa Port Complex, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'MMIA',
                'name' => 'Murtala Muhammed International Airport (MMIA)',
                'state' => 'Lagos',
                'location' => 'Ikeja, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'LAPC',
                'name' => 'Lagos Passport Command (LAPC)',
                'state' => 'Lagos',
                'location' => 'Ikoyi / Alausa, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'SEBC',
                'name' => 'Seme Border Command (SEBC)',
                'state' => 'Lagos',
                'location' => 'Seme Border, Badagry, Lagos',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_A',
                'code' => 'IDBC',
                'name' => 'Idiroko Border Command (IDBC)',
                'state' => 'Ogun',
                'location' => 'Idiroko Border, Ogun State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE B (KADUNA)
            [
                'zone_code' => 'ZONE_B',
                'code' => 'KDSC',
                'name' => 'Kaduna State Command (KDSC)',
                'state' => 'Kaduna',
                'location' => 'Kaduna, Kaduna State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'KTSC',
                'name' => 'Katsina State Command (KTSC)',
                'state' => 'Katsina',
                'location' => 'Katsina, Katsina State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'ZMSC',
                'name' => 'Zamfara State Command (ZMSC)',
                'state' => 'Zamfara',
                'location' => 'Gusau, Zamfara State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'KNSC',
                'name' => 'Kano State Command (KNSC)',
                'state' => 'Kano',
                'location' => 'Kano, Kano State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'ILBC',
                'name' => 'Illela Border Command (ILBC)',
                'state' => 'Sokoto',
                'location' => 'Illela Border, Sokoto State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'JIBC',
                'name' => 'Jibiya Border Command (JIBC)',
                'state' => 'Katsina',
                'location' => 'Jibia Border, Katsina State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'JGSC',
                'name' => 'Jigawa State Command (JGSC)',
                'state' => 'Jigawa',
                'location' => 'Dutse, Jigawa State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'SOSC',
                'name' => 'Sokoto State Command (SOSC)',
                'state' => 'Sokoto',
                'location' => 'Sokoto, Sokoto State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'ITSK',
                'name' => 'Immigration Training School Kano (ITSK)',
                'state' => 'Kano',
                'location' => 'Kano, Kano State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_B',
                'code' => 'ICSC',
                'name' => 'Immigration Command and Staff College (ICSC)',
                'state' => 'Sokoto',
                'location' => 'Sokoto, Sokoto State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE C (BAUCHI)
            [
                'zone_code' => 'ZONE_C',
                'code' => 'BASC',
                'name' => 'Bauchi State Command (BASC)',
                'state' => 'Bauchi',
                'location' => 'Bauchi, Bauchi State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_C',
                'code' => 'ADSC',
                'name' => 'Adamawa State Command (ADSC)',
                'state' => 'Adamawa',
                'location' => 'Yola, Adamawa State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_C',
                'code' => 'YBSC',
                'name' => 'Yobe State Command (YBSC)',
                'state' => 'Yobe',
                'location' => 'Damaturu, Yobe State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_C',
                'code' => 'BOSC',
                'name' => 'Borno State Command (BOSC)',
                'state' => 'Borno',
                'location' => 'Maiduguri, Borno State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_C',
                'code' => 'GMSC',
                'name' => 'Gombe State Command (GMSC)',
                'state' => 'Gombe',
                'location' => 'Gombe, Gombe State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_C',
                'code' => 'PLSC',
                'name' => 'Plateau State Command (PLSC)',
                'state' => 'Plateau',
                'location' => 'Jos, Plateau State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE D (MINNA)
            [
                'zone_code' => 'ZONE_D',
                'code' => 'NGSC',
                'name' => 'Niger State Command (NGSC)',
                'state' => 'Niger',
                'location' => 'Minna, Niger State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_D',
                'code' => 'KWSC',
                'name' => 'Kwara State Command (KWSC)',
                'state' => 'Kwara',
                'location' => 'Ilorin, Kwara State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_D',
                'code' => 'KBSC',
                'name' => 'Kebbi State Command (KBSC)',
                'state' => 'Kebbi',
                'location' => 'Birnin Kebbi, Kebbi State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_D',
                'code' => 'FCTC',
                'name' => 'Federal Capital Territory (FCTC)',
                'state' => 'FCT',
                'location' => 'Gwagwalada / Abuja, FCT',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE E (OWERRI)
            [
                'zone_code' => 'ZONE_E',
                'code' => 'IMSC',
                'name' => 'Imo State Command (IMSC)',
                'state' => 'Imo',
                'location' => 'Owerri, Imo State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'NITSOL',
                'name' => 'Nigeria Immigration Training Scholl Orlu (NITSOL)',
                'state' => 'Imo',
                'location' => 'Orlu, Imo State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'RVSC',
                'name' => 'River State Command (RVSC)',
                'state' => 'Rivers',
                'location' => 'Port Harcourt, Rivers State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'NITSA',
                'name' => 'Nigeria Immigration Training School Ahoada (NITSA)',
                'state' => 'Rivers',
                'location' => 'Ahoada, Rivers State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'AKSC',
                'name' => 'Akwa-Ibom State Command (AKSC)',
                'state' => 'Akwa Ibom',
                'location' => 'Uyo, Akwa Ibom State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'CRSC',
                'name' => 'Cross-River State Command (CRSC)',
                'state' => 'Cross River',
                'location' => 'Calabar, Cross River State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'EBSC',
                'name' => 'Ebonyi State Command (EBSC)',
                'state' => 'Ebonyi',
                'location' => 'Abakaliki, Ebonyi State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'ABSC',
                'name' => 'Abia State Command (ABSC)',
                'state' => 'Abia',
                'location' => 'Umuahia, Abia State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'MFBC',
                'name' => 'Mfun Border Command (MFBC)',
                'state' => 'Cross River',
                'location' => 'Mfun Border, Cross River State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_E',
                'code' => 'RVMC',
                'name' => 'River Marine Command, Onne (RVMC)',
                'state' => 'Rivers',
                'location' => 'Onne Port, Rivers State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE F (IBADAN)
            [
                'zone_code' => 'ZONE_F',
                'code' => 'OSSC',
                'name' => 'Osun State Command (OSSC)',
                'state' => 'Osun',
                'location' => 'Osogbo, Osun State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_F',
                'code' => 'ODSC',
                'name' => 'Ondo State Command (ODSC)',
                'state' => 'Ondo',
                'location' => 'Akure, Ondo State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_F',
                'code' => 'EKSC',
                'name' => 'Ekiti State Command (EKSC)',
                'state' => 'Ekiti',
                'location' => 'Ado-Ekiti, Ekiti State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_F',
                'code' => 'OYSC',
                'name' => 'Oyo State Command (OYSC)',
                'state' => 'Oyo',
                'location' => 'Agodi, Ibadan, Oyo State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE G (BENIN)
            [
                'zone_code' => 'ZONE_G',
                'code' => 'EDSC',
                'name' => 'Edo State Command (EDSC)',
                'state' => 'Edo',
                'location' => 'Benin City, Edo State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_G',
                'code' => 'ANSC',
                'name' => 'Anambra State Command (ANSC)',
                'state' => 'Anambra',
                'location' => 'Awka, Anambra State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_G',
                'code' => 'DTSC',
                'name' => 'Delta State Command (DTSC)',
                'state' => 'Delta',
                'location' => 'Asaba, Delta State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_G',
                'code' => 'BYSC',
                'name' => 'Bayelsa State Command (BYSC)',
                'state' => 'Bayelsa',
                'location' => 'Yenagoa, Bayelsa State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_G',
                'code' => 'ENSC',
                'name' => 'Enugu State Command (ENSC)',
                'state' => 'Enugu',
                'location' => 'Enugu, Enugu State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],

            // ZONE H (MAKURDI)
            [
                'zone_code' => 'ZONE_H',
                'code' => 'BNSC',
                'name' => 'Benue State Command (BNSC)',
                'state' => 'Benue',
                'location' => 'Makurdi, Benue State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_H',
                'code' => 'KGSC',
                'name' => 'Kogi State Command (KGSC)',
                'state' => 'Kogi',
                'location' => 'Lokoja, Kogi State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_H',
                'code' => 'TRSC',
                'name' => 'Taraba State Command (TRSC)',
                'state' => 'Taraba',
                'location' => 'Jalingo, Taraba State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
            [
                'zone_code' => 'ZONE_H',
                'code' => 'NASC',
                'name' => 'Nasarawa State command (NASC)',
                'state' => 'Nasarawa',
                'location' => 'Lafia, Nasarawa State',
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ],
        ];

        // 3. Seed NIS Headquarters, Abuja (ABJ-HQ) - preserving ID 1 and historical appointments
        $zoneD = $zones['ZONE_D'];
        $hqCenter = EnrollmentCenter::updateOrCreate(
            ['code' => 'ABJ-HQ'],
            [
                'name' => 'NIS Headquarters, Abuja',
                'state' => 'FCT',
                'address' => 'Nigeria Immigration Service Headquarters, Sauka, Airport Road, Abuja',
                'zone_id' => $zoneD->id,
                'zone_code' => 'ZONE_D',
                'zone_name' => 'Zone D (Minna)',
                'command_name' => 'NIS Service Headquarters / FCT Command',
                'daily_capacity' => 60,
                'time_slots' => $defaultSlots,
                'is_active' => true,
                'is_biometric_enabled' => true,
                'is_booking_enabled' => true,
            ]
        );

        // 4. Seed Commands and corresponding Enrollment Centers
        foreach ($commandsData as $cd) {
            $parentZone = $zones[$cd['zone_code']];

            $command = NisCommand::updateOrCreate(
                ['code' => $cd['code']],
                [
                    'zone_id' => $parentZone->id,
                    'name' => $cd['name'],
                    'state' => $cd['state'],
                    'location' => $cd['location'],
                    'is_biometric_enabled' => $cd['is_biometric_enabled'],
                    'is_booking_enabled' => $cd['is_booking_enabled'],
                    'is_active' => true,
                ]
            );

            // Create or update corresponding Enrollment Center for this Command
            $centerCode = 'EC-'.$cd['code'];
            EnrollmentCenter::updateOrCreate(
                ['code' => $centerCode],
                [
                    'name' => $cd['name'],
                    'state' => $cd['state'],
                    'address' => $cd['location'],
                    'zone_id' => $parentZone->id,
                    'command_id' => $command->id,
                    'zone_code' => $cd['zone_code'],
                    'zone_name' => $parentZone->name,
                    'command_name' => $cd['name'],
                    'daily_capacity' => $cd['is_booking_enabled'] ? 60 : 0,
                    'time_slots' => $cd['is_booking_enabled'] ? $defaultSlots : [],
                    'is_active' => true,
                    'is_biometric_enabled' => $cd['is_biometric_enabled'],
                    'is_booking_enabled' => $cd['is_booking_enabled'],
                ]
            );
        }

        // Link HQ Command to ABJ-HQ if FCTC command exists
        $fctCmd = NisCommand::where('code', 'FCTC')->first();
        if ($fctCmd && ! $hqCenter->command_id) {
            $hqCenter->update(['command_id' => $fctCmd->id]);
        }
    }
}
