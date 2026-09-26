<?php

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    RateLimiter::clear('public-lookup');
});

it('treats SQL injection in public lookups as plain data', function (string $payload) {
    $this->artisan('nis:demo')->assertSuccessful();

    // Rejected by validation (422) or simply not found (404): never a match, never an error.
    expect($this->getJson('/api/v1/public/track?'.http_build_query(['application_number' => $payload, 'passport_number' => $payload]))->status())->toBeIn([404, 422]);
    expect($this->getJson('/api/v1/public/verify-card?'.http_build_query(['card_number' => $payload, 'passport_number' => $payload]))->status())->toBeIn([404, 422]);

    expect(User::count())->toBeGreaterThan(0);
})->with([
    "' OR '1'='1",
    "1' OR 1=1--",
    "x'; DROP TABLE applications;--",
    "' UNION SELECT password FROM users--",
]);

it('treats SQL injection and wildcards in staff searches as plain text', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    asStaff(User::where('role', StaffRole::SuperAdmin)->firstOrFail());

    $this->getJson('/api/v1/staff/applications?search='.urlencode("' OR '1'='1"))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/staff/applications?search=%25')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/staff/cards?search=_')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/staff/applications?search=Mensah')->assertOk()->assertJsonCount(1, 'data');
});

it('hides database errors from API clients even in debug mode', function () {
    config(['app.debug' => true]);
    Route::get('/api/v1/public/_boom', fn () => throw new QueryException('pgsql', 'select secret_column from secret_table', [], new Exception('SQLSTATE[42P01]')));

    $response = $this->getJson('/api/v1/public/_boom')->assertServerError();

    expect($response->getContent())->not->toContain('secret_table')->not->toContain('SQLSTATE')->not->toContain('pgsql');
});

it('sends security headers on API responses and sign-in pages', function () {
    $api = $this->getJson('/api/v1/public/enrollment-centers')->assertOk();
    $api->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    expect($api->headers->has('X-Powered-By'))->toBeFalse();

    $page = $this->get('/login/applicant')->assertOk();
    expect($page->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain("script-src 'self';")->not->toContain('unsafe-eval')->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('escapes script injected into sign-in pages', function () {
    $this->from('/login/staff')
        ->post('/login/staff', ['identifier' => '"><script>alert(1)</script>', 'password' => 'wrong-password'])
        ->assertRedirect('/login/staff');

    $this->get('/login/staff')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&quot;&gt;&lt;script&gt;', false);
});

describe('malicious uploads', function () {
    beforeEach(function () {
        $this->artisan('nis:demo')->assertSuccessful();
        asApplicant(Applicant::where('email', 'kwame.mensah@example.com')->firstOrFail());
    });

    $upload = fn (string $name, string $bytes, string $type = 'passport_copy') => test()->post('/api/v1/applicant/draft/documents', [
        'type' => $type, 'file' => UploadedFile::fake()->createWithContent($name, $bytes),
    ], ['Accept' => 'application/json']);

    it('refuses the EICAR test virus', function () use ($upload) {
        $eicar = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        $upload('scan.pdf', "%PDF-1.4\n{$eicar}\n%%EOF")->assertUnprocessable();
    });

    it('refuses PDFs with JavaScript or launch actions, even when obfuscated', function (string $action) use ($upload) {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction << /S {$action} >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $upload('passport.pdf', $pdf)->assertUnprocessable();
    })->with(['/JavaScript /JS (app.alert(1))', '/J#61vaScript /JS (x)', '/Launch /F (cmd.exe)']);

    it('accepts a plain PDF', function () use ($upload) {
        $upload('passport.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n2 0 obj\n<< /Length 4 >>\nstream\n/JS \nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF")->assertCreated();
    });

    it('refuses images carrying PHP or script code', function () use ($upload) {
        $upload('photo.png', $this->pngBytes().'<?php system($_GET["c"]); ?>', 'photo')->assertUnprocessable();
    });

    it('refuses executables and scripts disguised as documents', function (string $name, string $bytes) use ($upload) {
        $upload($name, $bytes)->assertUnprocessable();
    })->with([
        ['passport.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\0", 64)],
        ['passport.jpg', '<?php echo shell_exec($_GET["c"]); ?>'],
        ['passport.png', '<html><script>alert(1)</script></html>'],
    ]);

    it('re-encodes images so hidden trailing data is removed', function () use ($upload) {
        $upload('photo.png', $this->pngBytes().str_repeat('HIDDEN-PAYLOAD', 10), 'photo')->assertCreated();

        $document = ApplicationDocument::latest('id')->firstOrFail();
        expect(Storage::disk('local')->get($document->path))->not->toContain('HIDDEN-PAYLOAD');
    });
});
