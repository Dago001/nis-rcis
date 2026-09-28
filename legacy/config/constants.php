<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * System Constants & Reference Enumerations
 */

define('APP_NAME', 'NIS-RCIS');
define('APP_FULL_NAME', 'NIS Residence Card Issuance System');
define('APP_PORTAL_TITLE', 'NIS Residence Card Portal');
define('APP_ORGANIZATION', 'Nigeria Immigration Service');
define('APP_DIRECTORATE', 'Directorate of Visa and Residency');
define('APP_DIVISION', 'developed by NIS web team ICT/Cybersecurity Directorate');
define('APP_COUNTRY', 'Federal Republic of Nigeria');
define('APP_VERSION', '1.0.0');

// Legal Protocol
define('DEFAULT_PROTOCOL', '');
define('DEFAULT_ISSUING_COUNTRY', 'FEDERAL REPUBLIC OF NIGERIA');
define('DEFAULT_MINT_IMPRINT', 'Nigerian Security Printing & Minting Co. Ltd. Lagos');

// User Roles
define('ROLE_SUPER_ADMIN', 'SuperAdmin');
define('ROLE_APPROVING_OFFICER', 'ApprovingOfficer');
define('ROLE_ISSUING_OFFICER', 'IssuingOfficer');
define('ROLE_INSPECTOR', 'Inspector');
define('ROLE_AUDITOR', 'Auditor');

// Card Statuses
define('STATUS_DRAFT', 'DRAFT');
define('STATUS_PENDING', 'PENDING_APPROVAL');
define('STATUS_APPROVED', 'APPROVED');
define('STATUS_ISSUED', 'ISSUED');
define('STATUS_RENEWED', 'RENEWED');
define('STATUS_REVOKED', 'REVOKED');
define('STATUS_EXPIRED', 'EXPIRED');

// Application Statuses (Online Public Portal Lifecycle)
define('STATUS_APP_PENDING', 'PENDING_APPROVAL');
define('STATUS_APP_APPROVED', 'APPROVED_FOR_BIOMETRICS');
define('STATUS_APP_QUERIED', 'QUERIED');
define('STATUS_APP_REJECTED', 'REJECTED');
define('STATUS_APP_CAPTURED', 'BIOMETRICS_CAPTURED');
define('STATUS_APP_READY', 'READY_FOR_COLLECTION');
define('STATUS_APP_ISSUED', 'ISSUED');

// Sovereign Nationalities List (Clean country names without demonyms or brackets)
$GLOBALS['NATIONALITIES'] = [
    'AFGHANISTAN' => 'Afghanistan',
    'ALBANIA' => 'Albania',
    'ALGERIA' => 'Algeria',
    'ANDORRA' => 'Andorra',
    'ANGOLA' => 'Angola',
    'ANTIGUA AND BARBUDA' => 'Antigua and Barbuda',
    'ARGENTINA' => 'Argentina',
    'ARMENIA' => 'Armenia',
    'AUSTRALIA' => 'Australia',
    'AUSTRIA' => 'Austria',
    'AZERBAIJAN' => 'Azerbaijan',
    'BAHAMAS' => 'Bahamas',
    'BAHRAIN' => 'Bahrain',
    'BANGLADESH' => 'Bangladesh',
    'BARBADOS' => 'Barbados',
    'BELARUS' => 'Belarus',
    'BELGIUM' => 'Belgium',
    'BELIZE' => 'Belize',
    'BENIN' => 'Benin',
    'BHUTAN' => 'Bhutan',
    'BOLIVIA' => 'Bolivia',
    'BOSNIA AND HERZEGOVINA' => 'Bosnia and Herzegovina',
    'BOTSWANA' => 'Botswana',
    'BRAZIL' => 'Brazil',
    'BRUNEI' => 'Brunei',
    'BULGARIA' => 'Bulgaria',
    'BURKINA FASO' => 'Burkina Faso',
    'BURUNDI' => 'Burundi',
    'CABO VERDE' => 'Cabo Verde',
    'CAMBODIA' => 'Cambodia',
    'CAMEROON' => 'Cameroon',
    'CANADA' => 'Canada',
    'CENTRAL AFRICAN REPUBLIC' => 'Central African Republic',
    'CHAD' => 'Chad',
    'CHILE' => 'Chile',
    'CHINA' => 'China',
    'COLOMBIA' => 'Colombia',
    'COMOROS' => 'Comoros',
    'CONGO' => 'Congo',
    'DEMOCRATIC REPUBLIC OF CONGO' => 'Democratic Republic of Congo',
    'COSTA RICA' => 'Costa Rica',
    'COTE D\'IVOIRE' => 'Cote d\'Ivoire',
    'CROATIA' => 'Croatia',
    'CUBA' => 'Cuba',
    'CYPRUS' => 'Cyprus',
    'CZECH REPUBLIC' => 'Czech Republic',
    'DENMARK' => 'Denmark',
    'DJIBOUTI' => 'Djibouti',
    'DOMINICA' => 'Dominica',
    'DOMINICAN REPUBLIC' => 'Dominican Republic',
    'ECUADOR' => 'Ecuador',
    'EGYPT' => 'Egypt',
    'EL SALVADOR' => 'El Salvador',
    'EQUATORIAL GUINEA' => 'Equatorial Guinea',
    'ERITREA' => 'Eritrea',
    'ESTONIA' => 'Estonia',
    'ESWATINI' => 'Eswatini',
    'ETHIOPIA' => 'Ethiopia',
    'FIJI' => 'Fiji',
    'FINLAND' => 'Finland',
    'FRANCE' => 'France',
    'GABON' => 'Gabon',
    'GAMBIA' => 'Gambia',
    'GEORGIA' => 'Georgia',
    'GERMANY' => 'Germany',
    'GHANA' => 'Ghana',
    'GREECE' => 'Greece',
    'GRENADA' => 'Grenada',
    'GUATEMALA' => 'Guatemala',
    'GUINEA' => 'Guinea',
    'GUINEA-BISSAU' => 'Guinea-Bissau',
    'GUYANA' => 'Guyana',
    'HAITI' => 'Haiti',
    'HONDURAS' => 'Honduras',
    'HUNGARY' => 'Hungary',
    'ICELAND' => 'Iceland',
    'INDIA' => 'India',
    'INDONESIA' => 'Indonesia',
    'IRAN' => 'Iran',
    'IRAQ' => 'Iraq',
    'IRELAND' => 'Ireland',
    'ISRAEL' => 'Israel',
    'ITALY' => 'Italy',
    'JAMAICA' => 'Jamaica',
    'JAPAN' => 'Japan',
    'JORDAN' => 'Jordan',
    'KAZAKHSTAN' => 'Kazakhstan',
    'KENYA' => 'Kenya',
    'KIRIBATI' => 'Kiribati',
    'NORTH KOREA' => 'North Korea',
    'SOUTH KOREA' => 'South Korea',
    'KUWAIT' => 'Kuwait',
    'KYRGYZSTAN' => 'Kyrgyzstan',
    'LAOS' => 'Laos',
    'LATVIA' => 'Latvia',
    'LEBANON' => 'Lebanon',
    'LESOTHO' => 'Lesotho',
    'LIBERIA' => 'Liberia',
    'LIBYA' => 'Libya',
    'LIECHTENSTEIN' => 'Liechtenstein',
    'LITHUANIA' => 'Lithuania',
    'LUXEMBOURG' => 'Luxembourg',
    'MADAGASCAR' => 'Madagascar',
    'MALAWI' => 'Malawi',
    'MALAYSIA' => 'Malaysia',
    'MALDIVES' => 'Maldives',
    'MALI' => 'Mali',
    'MALTA' => 'Malta',
    'MARSHALL ISLANDS' => 'Marshall Islands',
    'MAURITANIA' => 'Mauritania',
    'MAURITIUS' => 'Mauritius',
    'MEXICO' => 'Mexico',
    'MICRONESIA' => 'Micronesia',
    'MOLDOVA' => 'Moldova',
    'MONACO' => 'Monaco',
    'MONGOLIA' => 'Mongolia',
    'MONTENEGRO' => 'Montenegro',
    'MOROCCO' => 'Morocco',
    'MOZAMBIQUE' => 'Mozambique',
    'MYANMAR' => 'Myanmar',
    'NAMIBIA' => 'Namibia',
    'NAURU' => 'Nauru',
    'NEPAL' => 'Nepal',
    'NETHERLANDS' => 'Netherlands',
    'NEW ZEALAND' => 'New Zealand',
    'NICARAGUA' => 'Nicaragua',
    'NIGER' => 'Niger',
    'NIGERIA' => 'Nigeria',
    'NORTH MACEDONIA' => 'North Macedonia',
    'NORWAY' => 'Norway',
    'OMAN' => 'Oman',
    'PAKISTAN' => 'Pakistan',
    'PALAU' => 'Palau',
    'PALESTINE' => 'Palestine',
    'PANAMA' => 'Panama',
    'PAPUA NEW GUINEA' => 'Papua New Guinea',
    'PARAGUAY' => 'Paraguay',
    'PERU' => 'Peru',
    'PHILIPPINES' => 'Philippines',
    'POLAND' => 'Poland',
    'PORTUGAL' => 'Portugal',
    'QATAR' => 'Qatar',
    'ROMANIA' => 'Romania',
    'RUSSIA' => 'Russia',
    'RWANDA' => 'Rwanda',
    'SAINT KITTS AND NEVIS' => 'Saint Kitts and Nevis',
    'SAINT LUCIA' => 'Saint Lucia',
    'SAINT VINCENT AND THE GRENADINES' => 'Saint Vincent and the Grenadines',
    'SAMOA' => 'Samoa',
    'SAN MARINO' => 'San Marino',
    'SAO TOME AND PRINCIPE' => 'Sao Tome and Principe',
    'SAUDI ARABIA' => 'Saudi Arabia',
    'SENEGAL' => 'Senegal',
    'SERBIA' => 'Serbia',
    'SEYCHELLES' => 'Seychelles',
    'SIERRA LEONE' => 'Sierra Leone',
    'SINGAPORE' => 'Singapore',
    'SLOVAKIA' => 'Slovakia',
    'SLOVENIA' => 'Slovenia',
    'SOLOMON ISLANDS' => 'Solomon Islands',
    'SOMALIA' => 'Somalia',
    'SOUTH AFRICA' => 'South Africa',
    'SOUTH SUDAN' => 'South Sudan',
    'SPAIN' => 'Spain',
    'SRI LANKA' => 'Sri Lanka',
    'SUDAN' => 'Sudan',
    'SURINAME' => 'Suriname',
    'SWEDEN' => 'Sweden',
    'SWITZERLAND' => 'Switzerland',
    'SYRIA' => 'Syria',
    'TAIWAN' => 'Taiwan',
    'TAJIKISTAN' => 'Tajikistan',
    'TANZANIA' => 'Tanzania',
    'THAILAND' => 'Thailand',
    'TIMOR-LESTE' => 'Timor-Leste',
    'TOGO' => 'Togo',
    'TONGA' => 'Tonga',
    'TRINIDAD AND TOBAGO' => 'Trinidad and Tobago',
    'TUNISIA' => 'Tunisia',
    'TURKEY' => 'Turkey',
    'TURKMENISTAN' => 'Turkmenistan',
    'TUVALU' => 'Tuvalu',
    'UGANDA' => 'Uganda',
    'UKRAINE' => 'Ukraine',
    'UNITED ARAB EMIRATES' => 'United Arab Emirates',
    'UNITED KINGDOM' => 'United Kingdom',
    'UNITED STATES' => 'United States',
    'URUGUAY' => 'Uruguay',
    'UZBEKISTAN' => 'Uzbekistan',
    'VANUATU' => 'Vanuatu',
    'VATICAN CITY' => 'Vatican City',
    'VENEZUELA' => 'Venezuela',
    'VIETNAM' => 'Vietnam',
    'YEMEN' => 'Yemen',
    'ZAMBIA' => 'Zambia',
    'ZIMBABWE' => 'Zimbabwe',
    'OTHER' => 'Other Nationality'
];

// Complexion options
$GLOBALS['COMPLEXIONS'] = [
    'DARK' => 'Dark',
    'FAIR' => 'Fair',
    'EBONY' => 'Ebony'
];

// Eye Colors
$GLOBALS['EYE_COLORS'] = [
    'BLACK' => 'Black',
    'BROWN' => 'Brown',
    'GREY' => 'Grey',
    'BLUE' => 'Blue'
];

// Hair Colors
$GLOBALS['HAIR_COLORS'] = [
    'BLACK' => 'Black',
    'DARK BROWN' => 'Dark Brown',
    'BROWN' => 'Brown',
    'GREY' => 'Grey',
    'BALD' => 'Bald',
    'BLONDE' => 'Blonde'
];

// Blood Groups
$GLOBALS['BLOOD_GROUPS'] = [
    'O+' => 'O+',
    'O-' => 'O-',
    'A+' => 'A+',
    'A-' => 'A-',
    'B+' => 'B+',
    'B-' => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-'
];

// Emergency Contact Relationships (NIS Passport Portal Standard)
$GLOBALS['RELATIONSHIPS'] = [
    'SPOUSE' => 'Spouse',
    'FATHER' => 'Father',
    'MOTHER' => 'Mother',
    'BROTHER' => 'Brother',
    'SISTER' => 'Sister',
    'SON' => 'Son',
    'DAUGHTER' => 'Daughter',
    'UNCLE' => 'Uncle',
    'AUNT' => 'Aunt',
    'COUSIN' => 'Cousin',
    'NEPHEW' => 'Nephew',
    'NIECE' => 'Niece',
    'GUARDIAN' => 'Guardian',
    'OTHER' => 'Other'
];

// Official Processing & Enrollment Centers
$GLOBALS['ENROLLMENT_CENTERS'] = [
    'NIS_HQ' => 'NIS HQ'
];

// Keep alias for backwards compatibility
$GLOBALS['NIS_COMMANDS'] = $GLOBALS['ENROLLMENT_CENTERS'];

// Paystack Gateway Configuration
define('PAYSTACK_PUBLIC_KEY', 'pk_test_sample_nis_rcis_testkey');
define('RESIDENCE_CARD_FEE_NAIRA', 35000);
define('RESIDENCE_CARD_FEE_KOBO', 3500000);
