<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Private document storage (S3-compatible in production).
 *
 * - The real content type is detected from the bytes (finfo), never trusted
 *   from the file name or the browser.
 * - Every file passes the MalwareScanner (active PDF content, polyglot
 *   images, EICAR, optional ClamAV); images are re-encoded.
 * - Files get random names; nothing is web-served directly. Access is only
 *   through short-lived signed URLs issued after an authorization check.
 */
class DocumentStorage
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly MalwareScanner $scanner) {}

    public function disk(): string
    {
        return config('nis.documents_disk');
    }

    public function storeUpload(UploadedFile $file, DocumentType $type, array $owner, ?Model $uploadedBy): ApplicationDocument
    {
        $bytes = $file->get();

        return $this->storeBytes($bytes, $type, $owner, $uploadedBy, $file->getClientOriginalName());
    }

    /**
     * Store a base64 data URL (webcam photo / signature pad capture).
     */
    public function storeDataUrl(string $dataUrl, DocumentType $type, array $owner, ?Model $uploadedBy): ApplicationDocument
    {
        if (! preg_match('#^data:image/(png|jpe?g);base64,#', $dataUrl, $m)) {
            throw ValidationException::withMessages([$type->value => 'Invalid image capture.']);
        }

        $bytes = base64_decode(substr($dataUrl, strlen($m[0])), true);
        if ($bytes === false) {
            throw ValidationException::withMessages([$type->value => 'Invalid image capture.']);
        }

        return $this->storeBytes($bytes, $type, $owner, $uploadedBy, "{$type->value}-capture");
    }

    /**
     * @param  array{application_id?: int, draft_id?: int}  $owner
     */
    public function storeBytes(string $bytes, DocumentType $type, array $owner, ?Model $uploadedBy, string $originalName): ApplicationDocument
    {
        $size = strlen($bytes);
        if ($size === 0 || $size > config('nis.max_upload_kb') * 1024) {
            throw ValidationException::withMessages([$type->value => "{$type->label()}: the file must be smaller than ".(config('nis.max_upload_kb') / 1024).' MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        if (! in_array($mime, $type->allowedMimeTypes(), true)) {
            throw ValidationException::withMessages([$type->value => "{$type->label()}: file type {$mime} is not allowed."]);
        }

        if (str_starts_with($mime, 'image/') && @getimagesizefromstring($bytes) === false) {
            throw ValidationException::withMessages([$type->value => "{$type->label()}: the image is corrupted."]);
        }

        $bytes = $this->scanner->inspect($bytes, $mime, $type->value);
        $size = strlen($bytes);

        $path = sprintf('%s/%s/%s.%s', $type->value, now()->format('Y/m'), Str::uuid(), self::EXTENSIONS[$mime]);
        Storage::disk($this->disk())->put($path, $bytes, ['visibility' => 'private']);

        $ownerQuery = ApplicationDocument::query()->where('type', $type)->where('is_current', true)->where($owner);
        $previous = (clone $ownerQuery)->max('version') ?? 0;
        $ownerQuery->update(['is_current' => false]);

        $document = new ApplicationDocument([
            ...$owner,
            'type' => $type,
            'disk' => $this->disk(),
            'path' => $path,
            'original_name' => Str::limit(basename($originalName), 200, ''),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash('sha256', $bytes),
            'version' => $previous + 1,
            'is_current' => true,
        ]);

        if ($uploadedBy) {
            $document->uploadedBy()->associate($uploadedBy);
        }
        $document->save();

        return $document;
    }

    public function temporaryUrl(ApplicationDocument $document): string
    {
        return Storage::disk($document->disk)->temporaryUrl(
            $document->path,
            now()->addMinutes(config('nis.document_url_ttl_minutes')),
        );
    }

    public function temporaryUrlForPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk($this->disk())->temporaryUrl($path, now()->addMinutes(config('nis.document_url_ttl_minutes')));
    }
}
