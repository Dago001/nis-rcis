<?php

namespace App\Support;

use App\Rules\NigerianLga;
use Illuminate\Validation\Rule;

/**
 * Validation rules for application particulars (shared by the online
 * portal, assisted applications and card edits).
 */
class ApplicationRules
{
    /** Names: letters (any alphabet), spaces, hyphens, apostrophes and dots. */
    public const NAME = "/^[\pL][\pL .'-]*$/u";

    /** Phone numbers in international (E.164) form: + and 7-15 digits. */
    public const PHONE = '/^\+[1-9][0-9]{6,14}$/';

    /** Places, professions and short descriptions: letters and basic punctuation. */
    public const TEXT = "/^[\pL][\pL .,'()\/&-]*$/u";

    /** Street addresses: letters, digits and common address punctuation. */
    public const ADDRESS = "/^[\pL0-9][\pL0-9 .,'()\/#&:;-]*$/u";

    /**
     * @param  string|null  $keepNationality  an existing card's nationality, still accepted when
     *                                        staff edit it (cards issued before the ECOWAS-only rule)
     */
    public static function particulars(?string $keepNationality = null): array
    {
        return [
            'category' => ['sometimes', 'required', 'string', Rule::in(['WORK', 'BUSINESS', 'STUDENT', 'RELIGIOUS'])],
            'surname' => ['required', 'string', 'max:100', 'regex:'.self::NAME],
            'forenames' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            // ECOWAS residence card: citizens of ECOWAS member states only.
            // Strictly disallow non-ECOWAS countries (e.g. United Kingdom, UK, Great Britain, British, etc.)
            'nationality' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) use ($keepNationality) {
                $valUpper = strtoupper(trim((string) $value));
                if ($keepNationality && $valUpper === strtoupper(trim((string) $keepNationality))) {
                    return;
                }
                $ineligible = ['UNITED KINGDOM', 'UK', 'GREAT BRITAIN', 'BRITISH', 'GBR', 'ROYAUME-UNI'];
                if (in_array($valUpper, $ineligible, true)) {
                    $fail('United Kingdom is not an ECOWAS member state and cannot be selected for an ECOWAS Residence Card.');
                    return;
                }
                $allowed = [...config('nis.ecowas_member_states', []), ...config('nis.ecowas_legacy_names', [])];
                $upperAllowed = array_map('strtoupper', array_filter($allowed));
                if (! in_array($valUpper, $upperAllowed, true)) {
                    $fail('The ECOWAS residence card is only for citizens of ECOWAS member states.');
                }
            }],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1900-01-01'],
            'place_of_birth' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'sex' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'height' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+(\.[0-9]{1,2})?\s?(m|cm|M|CM)?$/'],
            'complexion' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'eye_color' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'hair_color' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'distinguished_features' => ['nullable', 'string', 'max:150', 'regex:'.self::TEXT],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UNKNOWN'])],
            'profession' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country' => ['required', 'string', 'max:100'],
            'home_country_state' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_city' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_district' => ['nullable', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_address' => ['required', 'string', 'max:500', 'regex:'.self::ADDRESS],
            'home_country_postal_code' => ['nullable', 'string', 'max:30'],
            'domicile' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'domicile_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'domicile_lga' => ['required', 'string', new NigerianLga('domicile_state')],
            'change_of_address' => ['nullable', 'string', 'max:500', 'regex:'.self::ADDRESS],
            'passport_number' => ['required', 'string', 'regex:/^[A-Z0-9-]{4,20}$/i'],
            'passport_issue_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'passport_expiry' => ['required', 'date', 'after:passport_issue_date'],
            'national_id_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9-]{4,30}$/i'],
            'tax_id_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9-]{4,30}$/i'],
            'emergency_contact_name' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'emergency_contact_relation' => ['required', 'string', 'max:50', 'regex:'.self::NAME],
            'emergency_contact_phone' => ['required', 'string', 'regex:'.self::PHONE],
            'emergency_contact_address' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'emergency_contact_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'emergency_contact_lga' => ['required', 'string', new NigerianLga('emergency_contact_state')],
            'emergency_contact_id_type' => ['nullable', 'string', 'max:50'],
            'home_country_emergency_contact_name' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'home_country_emergency_contact_relation' => ['required', 'string', 'max:50', 'regex:'.self::NAME],
            'home_country_emergency_contact_phone' => ['required', 'string', 'regex:'.self::PHONE],
            'home_country_emergency_contact_country' => ['required', 'string', 'max:100'],
            'home_country_emergency_contact_state' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_emergency_contact_city' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_emergency_contact_district' => ['nullable', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_emergency_contact_address' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'home_country_emergency_contact_postal_code' => ['nullable', 'string', 'max:30'],
            'home_country_emergency_contact_id_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** Expatriate quota approval (checked with the Ministry of Interior). */
    public static function quota(): array
    {
        return [
            'quota_reference' => ['nullable', 'string', 'max:60', 'regex:#^[A-Z0-9][A-Z0-9/ .-]*$#'],
            'employer_name' => ['nullable', 'required_with:quota_reference', 'string', 'max:150', 'regex:'.self::TEXT],
        ];
    }

    public static function contact(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.self::PHONE],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public static function appointment(): array
    {
        return [
            'enrollment_center_id' => ['required', 'integer', Rule::exists('enrollment_centers', 'id')->where('is_active', true)],
            'appointment_date' => ['required', 'date', 'after:today', 'before:+60 days'],
            'appointment_time' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * Normalise free text the way the legacy system stored it (upper case).
     */
    public static function normalise(array $data): array
    {
        $upper = ['quota_reference', 'employer_name', 'surname', 'forenames', 'nationality', 'place_of_birth', 'profession', 'passport_number',
            'home_country', 'home_country_state', 'home_country_city', 'home_country_district',
            'home_country_emergency_contact_country', 'home_country_emergency_contact_state', 'home_country_emergency_contact_city', 'home_country_emergency_contact_district',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_id_type',
            'home_country_emergency_contact_name', 'home_country_emergency_contact_relation', 'home_country_emergency_contact_id_type',
            'distinguished_features', 'complexion', 'eye_color', 'hair_color'];

        // Convert empty strings for optional fields into null so validation passes
        foreach (['national_id_number', 'tax_id_number', 'change_of_address', 'quota_reference', 'employer_name', 'height', 'complexion', 'eye_color', 'hair_color', 'distinguished_features', 'blood_group', 'home_country_district', 'home_country_postal_code', 'home_country_emergency_contact_district', 'home_country_emergency_contact_postal_code'] as $optKey) {
            if (array_key_exists($optKey, $data) && (is_null($data[$optKey]) || trim((string) $data[$optKey]) === '')) {
                $data[$optKey] = null;
            }
        }

        // Phone numbers are stored as + and digits only.
        foreach (['phone', 'emergency_contact_phone', 'home_country_emergency_contact_phone'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $raw = trim($data[$key]);
                if (preg_match('/^\s*\+?[0-9 ()-]+$/', $raw)) {
                    $digits = preg_replace('/\D/', '', $raw);
                    if (str_starts_with($raw, '+')) {
                        $data[$key] = '+'.$digits;
                    } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
                        $data[$key] = '+234'.substr($digits, 1);
                    } elseif (str_starts_with($digits, '234')) {
                        $data[$key] = '+'.$digits;
                    } else {
                        $data[$key] = '+'.ltrim($digits, '0');
                    }
                }
            }
        }

        foreach ($upper as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = mb_strtoupper(trim($data[$key]));
            }
        }

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        foreach (['home_country_address', 'domicile', 'emergency_contact_address', 'home_country_emergency_contact_address', 'change_of_address', 'home_country', 'home_country_state', 'home_country_city', 'home_country_district', 'home_country_postal_code', 'home_country_emergency_contact_country', 'home_country_emergency_contact_state', 'home_country_emergency_contact_city', 'home_country_emergency_contact_district', 'home_country_emergency_contact_postal_code'] as $addrKey) {
            if (isset($data[$addrKey]) && is_string($data[$addrKey])) {
                $data[$addrKey] = trim($data[$addrKey]);
            }
        }

        if (empty($data['home_country']) && ! empty($data['nationality'])) {
            $data['home_country'] = $data['nationality'];
        }
        if (empty($data['home_country_emergency_contact_country'])) {
            $data['home_country_emergency_contact_country'] = $data['home_country'] ?? ($data['nationality'] ?? null);
        }

        $data['distinguished_features'] = ($data['distinguished_features'] ?? null) ?: 'NONE';
        $data['blood_group'] = ($data['blood_group'] ?? null) ?: 'UNKNOWN';

        if (isset($data['category']) && is_string($data['category'])) {
            $data['category'] = mb_strtoupper(trim($data['category']));
        }

        return $data;
    }
}
