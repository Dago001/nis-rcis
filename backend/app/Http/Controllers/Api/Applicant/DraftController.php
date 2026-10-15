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

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['NEW', 'RENEWAL', 'REPLACE'])],
            'current_step' => ['required', 'integer', 'between:1,8'],
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
        ]);

        $draft = ApplicationDraft::updateOrCreate(
            ['applicant_id' => $this->applicant($request)->id],
            ['type' => $data['type'], 'current_step' => $data['current_step'], 'data' => $data['data']],
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
