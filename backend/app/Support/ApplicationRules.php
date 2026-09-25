<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Validation rules for application particulars (shared by the online
 * portal, assisted applications and card edits).
 */
class ApplicationRules
{
    public static function particulars(): array
    {
        return [
            'surname' => ['required', 'string', 'max:100'],
            'forenames' => ['required', 'string', 'max:150'],
            'nationality' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1900-01-01'],
            'place_of_birth' => ['required', 'string', 'max:150'],
            'sex' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'height' => ['nullable', 'string', 'max:30'],
            'complexion' => ['nullable', 'string', 'max:50'],
            'eye_color' => ['nullable', 'string', 'max:50'],
            'hair_color' => ['nullable', 'string', 'max:50'],
            'distinguished_features' => ['nullable', 'string', 'max:150'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UNKNOWN'])],
            'profession' => ['required', 'string', 'max:150'],
            'domicile' => ['required', 'string', 'max:500'],
            'change_of_address' => ['nullable', 'string', 'max:500'],
            'passport_number' => ['required', 'string', 'regex:/^[A-Z0-9]{6,15}$/'],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry' => ['required', 'date', 'after:+6 months'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'tax_id_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:150'],
            'emergency_contact_relation' => ['required', 'string', 'max:50'],
            'emergency_contact_phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'emergency_contact_address' => ['required', 'string', 'max:500'],
        ];
    }

    public static function contact(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
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
        $upper = ['surname', 'forenames', 'nationality', 'place_of_birth', 'profession', 'passport_number',
            'emergency_contact_name', 'emergency_contact_relation', 'distinguished_features'];

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
