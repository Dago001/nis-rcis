<?php

namespace Tests;

use Database\Seeders\EnrollmentCenterSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected string $seeder = EnrollmentCenterSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Tiny valid PNG as a data URL (webcam / signature capture). */
    protected function pngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->pngBytes());
    }

    protected function pngBytes(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }
}
