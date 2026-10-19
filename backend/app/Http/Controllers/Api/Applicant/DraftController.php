<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Enums\DocumentType;
use App\Models\Applicant;
use App\Models\ApplicationDraft;
use App\Services\DocumentStorage;
use App\Services\PhotoQuality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Save & exit" for the 7-step application wizard.
 */
class DraftController
{
    public function show(Request $request): JsonResponse
    {
        $draft = $this->applicant($request)->draft()->with('documents')->first();

        return response()->json(['draft' => $draft ? $this->present($draft) : null]);
    }

    public function checkPassport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'passport_number' => ['required', 'string', 'max:20'],
            'travel_document_type' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'string', Rule::in(['NEW', 'RENEWAL', 'REPLACE'])],
            'renewal_card_number' => ['nullable', 'string', 'max:30'],
            'principal_application_id' => ['nullable', 'integer'],
        ]);

        $passport = strtoupper(trim($data['passport_number']));
        if (! preg_match('/^[A-Za-z0-9-]{4,20}$/', $passport)) {
            return response()->json([
                'valid' => false,
                'message' => '4–20 letters and digits (hyphens allowed), no spaces.',
                'errors' => ['passport_number' => ['4–20 letters and digits (hyphens allowed), no spaces.']],
            ], 422);
        }

        $applicant = $this->applicant($request);
        $type = strtoupper(trim($data['type'] ?? 'NEW'));
        $forDependant = ! empty($data['principal_application_id']);

        $renewalCard = null;
        if (in_array($type, ['RENEWAL', 'REPLACE'], true) && ! empty($data['renewal_card_number'])) {
            $renewalCard = \App\Models\ResidenceCard::where('card_number', $data['renewal_card_number'])->first();
        }

        $query = \App\Models\Application::query()
            ->where(fn ($q) => $forDependant
                ? $q->where('passport_number', $passport)
                : $q->where(fn ($own) => $own->where('applicant_id', $applicant->id)->whereNull('principal_application_id'))->orWhere('passport_number', $passport))
            ->whereNotIn('status', [\App\Enums\ApplicationStatus::Rejected, \App\Enums\ApplicationStatus::Issued]);

        if ($renewalCard) {
            $ignoreAppId = $renewalCard->application?->id;
            if ($ignoreAppId) {
                $query->where('id', '!=', $ignoreAppId);
            }
            $query->where(function ($q) use ($renewalCard) {
                $q->whereNull('card_id')->orWhere('card_id', '!=', $renewalCard->id);
            });
        }

        $query->whereDoesntHave('card', function ($cardQuery) {
            $cardQuery->whereIn('status', [\App\Enums\CardStatus::Issued, \App\Enums\CardStatus::Renewed]);
        });

        $open = $query->first();

        if ($open) {
            return response()->json([
                'valid' => false,
                'message' => 'This passport number is already associated with an existing record. Please review your details or contact support if you believe this is an error.',
                'errors' => [
                    'passport_number' => ['This passport number is already associated with an existing record. Please review your details or contact support if you believe this is an error.'],
                ],
            ], 422);
        }

        return response()->json(['valid' => true]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['NEW', 'RENEWAL', 'REPLACE'])],
            'current_step' => ['required', 'integer', 'between:1,8'],
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
        ]);

        $payloadData = $data['data'];
        $payloadData['type'] = $data['type'];
        if (! empty($payloadData['category']) && is_string($payloadData['category'])) {
            $payloadData['category'] = strtoupper(trim($payloadData['category']));
        }

        // Validate passport uniqueness when advancing beyond Step 1
        if ($data['current_step'] > 1 && ! empty($payloadData['passport_number'])) {
            $passportCheck = new Request([
                'passport_number' => $payloadData['passport_number'],
                'travel_document_type' => $payloadData['travel_document_type'] ?? null,
                'type' => $data['type'],
                'renewal_card_number' => $payloadData['renewal_card_number'] ?? null,
                'principal_application_id' => $payloadData['principal_application_id'] ?? null,
            ]);
            $passportCheck->setUserResolver($request->getUserResolver());
            $checkRes = $this->checkPassport($passportCheck);
            if ($checkRes->getStatusCode() !== 200) {
                return $checkRes;
            }
        }

        $draft = ApplicationDraft::updateOrCreate(
            ['applicant_id' => $this->applicant($request)->id],
            ['type' => $data['type'], 'current_step' => $data['current_step'], 'data' => $payloadData],
        );

        return response()->json(['draft' => $this->present($draft->load('documents'))]);
    }

    public function destroy(Request $request): Response
    {
        $this->applicant($request)->draft()->delete();

        return response()->noContent();
    }

    public function uploadDocument(Request $request, DocumentStorage $storage): JsonResponse
    {
        $mimes = $request->input('type') === DocumentType::Photo->value
            ? 'mimes:jpg,jpeg,png'
            : 'mimes:pdf,jpg,jpeg,png';

        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)->only(DocumentType::applicantUploadable())],
            'file' => ['required', 'file', $mimes, 'max:'.config('nis.max_upload_kb')],
        ], [
            'file.max' => 'The file must be smaller than '.(config('nis.max_upload_kb') / 1024).' MB.',
            'file.mimes' => 'Unsupported format. Allowed formats: '.($request->input('type') === DocumentType::Photo->value ? 'JPG, PNG' : 'PDF, JPG, PNG').'.',
        ]);

        $applicant = $this->applicant($request);
        $draft = ApplicationDraft::firstOrCreate(['applicant_id' => $applicant->id], ['type' => 'NEW', 'current_step' => 1, 'data' => []]);

        if ($data['type'] === DocumentType::Photo->value && ($problems = app(PhotoQuality::class)->problems((string) $request->file('file')->get()))) {
            throw ValidationException::withMessages(['file' => $problems]);
        }

        $uploaded = $storage->storeUpload($request->file('file'), DocumentType::from($data['type']), ['draft_id' => $draft->id], $applicant);

        return response()->json([
            'draft' => $this->present($draft->load('documents')),
            'uploaded_document' => [
                'id' => $uploaded->id,
                'url' => $storage->temporaryUrl($uploaded),
                'mime_type' => $uploaded->mime_type,
            ],
        ], 201);
    }

    /**
     * Delete an uploaded draft document.
     */
    public function deleteDocument(Request $request, int $document, DocumentStorage $storage): JsonResponse
    {
        $draft = $this->applicant($request)->draft()->firstOrFail();
        $file = $draft->documents()->findOrFail($document);
        $storage->delete($file);

        return response()->json([
            'draft' => $this->present($draft->load('documents')),
        ]);
    }

    /**
     * Short-lived link to view one of the applicant's own draft documents.
     */
    public function document(Request $request, int $document, DocumentStorage $storage): JsonResponse
    {
        $draft = $this->applicant($request)->draft()->firstOrFail();
        $file = $draft->documents()->findOrFail($document);

        return response()->json(['url' => $storage->temporaryUrl($file), 'mime_type' => $file->mime_type]);
    }

    private function present(ApplicationDraft $draft): array
    {
        $storage = app(DocumentStorage::class);

        return [
            'type' => $draft->type,
            'current_step' => $draft->current_step,
            'data' => $draft->data,
            'documents' => $draft->documents->map(function ($d) use ($storage) {
                $url = null;
                try {
                    $url = $storage->temporaryUrl($d);
                } catch (\Throwable) {
                    $url = null;
                }

                return [
                    'id' => $d->id,
                    'type' => $d->type->value,
                    'label' => $d->type->label(),
                    'original_name' => $d->original_name,
                    'size_bytes' => $d->size_bytes,
                    'mime_type' => $d->mime_type,
                    'url' => $url,
                ];
            })->values(),
            'saved_at' => $draft->updated_at,
        ];
    }

    public function renewalSource(Request $request): JsonResponse
    {
        $applicant = $this->applicant($request);

        $cardNum = trim((string) ($request->query('renewal_card_number') ?: $request->query('card')));
        $passportNum = trim((string) $request->query('passport_number'));
        $dependantId = $request->query('dependant_id');
        $principalAppId = $request->query('principal_application_id') ?: $request->query('principal');
        $relationship = strtoupper(trim((string) ($request->query('dependant_relationship') ?: $request->query('for'))));

        $isDependant = ! empty($principalAppId) || in_array($relationship, ['SPOUSE', 'CHILD'], true) || ! empty($dependantId);

        $query = \App\Models\Application::query()
            ->with(['card', 'documents'])
            ->whereNotIn('status', [\App\Enums\ApplicationStatus::Rejected]);

        if ($isDependant) {
            $query->where(function ($q) use ($applicant) {
                $q->where('applicant_id', $applicant->id)
                  ->orWhere('email', $applicant->email);
            })->whereNotNull('principal_application_id');
            if ($passportNum) {
                $query->where('passport_number', $passportNum);
            }
            if ($dependantId) {
                $query->where('id', $dependantId);
            }
        } elseif ($cardNum) {
            $card = \App\Models\ResidenceCard::where('card_number', $cardNum)->first();
            if ($card && ($card->applicant_id === $applicant->id || ($card->applicant_id === null && $card->passport_number === $passportNum) || $card->email === $applicant->email)) {
                if ($card->application_id) {
                    $query->where('id', $card->application_id);
                } else {
                    $query->where('card_id', $card->id);
                }
            } else {
                return response()->json(['found' => false, 'message' => 'No residence card found matching your records.']);
            }
        } else {
            $query->where(function ($q) use ($applicant) {
                $q->where('applicant_id', $applicant->id)
                  ->orWhere('email', $applicant->email);
            })->whereNull('principal_application_id');
            if ($passportNum) {
                $query->where('passport_number', $passportNum);
            }
        }

        $sourceApp = (clone $query)
            ->whereIn('status', [
                \App\Enums\ApplicationStatus::ApprovedForBiometrics,
                \App\Enums\ApplicationStatus::BiometricsCaptured,
                \App\Enums\ApplicationStatus::ReadyForCollection,
                \App\Enums\ApplicationStatus::Issued,
                \App\Enums\ApplicationStatus::Vetted,
                \App\Enums\ApplicationStatus::Submitted,
            ])
            ->latest('id')
            ->first();

        if (! $sourceApp) {
            $sourceApp = $query->latest('id')->first();
        }

        if (! $sourceApp) {
            return response()->json([
                'found' => false,
                'message' => 'No previous application found to pre-populate renewal particulars.',
            ]);
        }

        $cardNumber = $sourceApp->card?->card_number ?? $sourceApp->renewal_of_card_id ?? $cardNum;

        $particulars = [
            'surname' => $sourceApp->surname ?? '',
            'forenames' => $sourceApp->forenames ?? '',
            'nationality' => $sourceApp->nationality ?? '',
            'date_of_birth' => $sourceApp->date_of_birth ? $sourceApp->date_of_birth->format('Y-m-d') : '',
            'place_of_birth' => $sourceApp->place_of_birth ?? '',
            'sex' => $sourceApp->sex ?? '',
            'height' => $sourceApp->height ?? '',
            'complexion' => $sourceApp->complexion ?? '',
            'eye_color' => $sourceApp->eye_color ?? '',
            'hair_color' => $sourceApp->hair_color ?? '',
            'distinguished_features' => $sourceApp->distinguished_features ?? '',
            'blood_group' => $sourceApp->blood_group ?? '',
            'profession' => $sourceApp->profession ?? '',
            'home_country_address' => $sourceApp->home_country_address ?? '',
            'domicile' => $sourceApp->domicile ?? '',
            'domicile_state' => $sourceApp->domicile_state ?? '',
            'domicile_lga' => $sourceApp->domicile_lga ?? '',
            'change_of_address' => $sourceApp->change_of_address ?? '',
            'passport_number' => $sourceApp->passport_number ?? '',
            'passport_issue_date' => $sourceApp->passport_issue_date ? $sourceApp->passport_issue_date->format('Y-m-d') : '',
            'passport_expiry' => $sourceApp->passport_expiry ? $sourceApp->passport_expiry->format('Y-m-d') : '',
            'national_id_number' => $sourceApp->national_id_number ?? '',
            'tax_id_number' => $sourceApp->tax_id_number ?? '',
            'emergency_contact_name' => $sourceApp->emergency_contact_name ?? '',
            'emergency_contact_relation' => $sourceApp->emergency_contact_relation ?? '',
            'emergency_contact_phone' => $sourceApp->emergency_contact_phone ?? '',
            'emergency_contact_address' => $sourceApp->emergency_contact_address ?? '',
            'emergency_contact_state' => $sourceApp->emergency_contact_state ?? '',
            'emergency_contact_lga' => $sourceApp->emergency_contact_lga ?? '',
            'category' => $sourceApp->category ?? 'WORK',
            'employer_name' => $sourceApp->employer_name ?? '',
            'quota_reference' => $sourceApp->quota_reference ?? '',
            'phone' => $sourceApp->phone ?? '',
            'email' => $sourceApp->email ?? '',
        ];

        return response()->json([
            'found' => true,
            'source_application_id' => $sourceApp->id,
            'source_application_number' => $sourceApp->application_number,
            'renewal_card_number' => $cardNumber,
            'particulars' => $particulars,
        ]);
    }

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}
