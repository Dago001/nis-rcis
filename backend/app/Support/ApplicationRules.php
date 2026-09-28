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

    public static function particulars(): array
    {
        return [
            'surname' => ['required', 'string', 'max:100', 'regex:'.self::NAME],
            'forenames' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'nationality' => ['required', 'string', 'max:100', 'regex:'.self::TEXT, Rule::notIn(['NIGERIA', 'NIGERIAN'])],
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
            'domicile' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'domicile_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'domicile_lga' => ['required', 'string', new NigerianLga('domicile_state')],
            'change_of_address' => ['nullable', 'string', 'max:500', 'regex:'.self::ADDRESS],
            'passport_number' => ['required', 'string', 'regex:/^[A-Z0-9]{6,15}$/'],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry' => ['required', 'date', 'after:+6 months'],
            'national_id_number' => ['nullable', 'string', 'regex:/^[0-9]{11}$/'],
            'tax_id_number' => ['nullable', 'string', 'regex:/^[0-9-]{6,20}$/'],
            'emergency_contact_name' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'emergency_contact_relation' => ['required', 'string', 'max:50', 'regex:'.self::NAME],
            'emergency_contact_phone' => ['required', 'string', 'regex:'.self::PHONE],
            'emergency_contact_address' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'emergency_contact_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'emergency_contact_lga' => ['required', 'string', new NigerianLga('emergency_contact_state')],
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
            'emergency_contact_name', 'emergency_contact_relation', 'distinguished_features', 'complexion', 'eye_color', 'hair_color'];

        // Phone numbers are stored as + and digits only.
        foreach (['phone', 'emergency_contact_phone'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && preg_match('/^\s*\+?[0-9 ()-]+$/', $data[$key])) {
                $data[$key] = '+'.preg_replace('/\D/', '', $data[$key]);
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

        $data['distinguished_features'] = ($data['distinguished_features'] ?? null) ?: 'NONE';
        $data['blood_group'] = ($data['blood_group'] ?? null) ?: 'UNKNOWN';

        return $data;
    }
}
