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
            'current_step' => ['required', 'integer', 'between:1,7'],
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
        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)->only(DocumentType::applicantUploadable())],
            'file' => ['required', 'file', 'max:'.config('nis.max_upload_kb')],
        ], ['file.max' => 'The file must be smaller than '.(config('nis.max_upload_kb') / 1024).' MB.']);

        $applicant = $this->applicant($request);
        $draft = ApplicationDraft::firstOrCreate(['applicant_id' => $applicant->id], ['type' => 'NEW', 'current_step' => 1, 'data' => []]);

        if ($data['type'] === DocumentType::Photo->value && ($problems = app(PhotoQuality::class)->problems((string) $request->file('file')->get()))) {
            throw ValidationException::withMessages(['file' => $problems]);
        }

        $storage->storeUpload($request->file('file'), DocumentType::from($data['type']), ['draft_id' => $draft->id], $applicant);

        return response()->json(['draft' => $this->present($draft->load('documents'))], 201);
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
        return [
            'type' => $draft->type,
            'current_step' => $draft->current_step,
            'data' => $draft->data,
            'documents' => $draft->documents->map(fn ($d) => [
                'id' => $d->id, 'type' => $d->type->value, 'label' => $d->type->label(),
                'original_name' => $d->original_name, 'size_bytes' => $d->size_bytes, 'mime_type' => $d->mime_type,
            ])->values(),
            'saved_at' => $draft->updated_at,
        ];
    }

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}
