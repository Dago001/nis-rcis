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

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}
