<?php

namespace App\Services;

/**
 * Automatic checks on an uploaded passport photograph, based on the ICAO
 * portrait guidelines: size and shape, exposure, contrast, sharpness and a
 * plain light background. Faces are checked by the officer at biometrics.
 */
class PhotoQuality
{
    public const MIN_WIDTH = 300;

    public const MIN_HEIGHT = 350;

    /** @return list<string> problems in plain language; empty when the photo is acceptable */
    public function problems(string $bytes): array
    {
        if (! config('nis.photo_quality_check') || ! extension_loaded('gd')) {
            return [];
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return ['The photograph could not be read.'];
        }

        $problems = [];
        $w = imagesx($image);
        $h = imagesy($image);

        if ($w < self::MIN_WIDTH || $h < self::MIN_HEIGHT) {
            $problems[] = sprintf('The photograph is too small (%d × %d pixels). Use at least %d × %d pixels.', $w, $h, self::MIN_WIDTH, self::MIN_HEIGHT);
        }
        $ratio = $h / max(1, $w);
        if ($ratio < 1.0 || $ratio > 1.6) {
            $problems[] = 'Use a portrait (upright) passport-style photograph, taller than it is wide.';
        }

        // Work on a small grey copy for the pixel statistics.
        $sw = 200;
        $sh = max(1, (int) round($h * $sw / max(1, $w)));
        $small = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagedestroy($image);

        $lum = [];
        $sum = 0.0;
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $l = 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255);
                $lum[$y][$x] = $l;
                $sum += $l;
            }
        }
        $n = $sw * $sh;
        $mean = $sum / $n;
        $var = 0.0;
        foreach ($lum as $row) {
            foreach ($row as $l) {
                $var += ($l - $mean) ** 2;
            }
        }
        $std = sqrt($var / $n);

        if ($mean < 60) {
            $problems[] = 'The photograph is too dark. Take it in good, even light.';
        } elseif ($mean > 235) {
            $problems[] = 'The photograph is over-exposed (too bright).';
        }
        if ($std < 20) {
            $problems[] = 'The photograph has almost no contrast. Make sure your face is clearly visible.';
        }

        // Sharpness: variance of the Laplacian.
        $lap = [];
        for ($y = 1; $y < $sh - 1; $y++) {
            for ($x = 1; $x < $sw - 1; $x++) {
                $lap[] = 4 * $lum[$y][$x] - $lum[$y - 1][$x] - $lum[$y + 1][$x] - $lum[$y][$x - 1] - $lum[$y][$x + 1];
            }
        }
        if ($lap !== []) {
            $lm = array_sum($lap) / count($lap);
            $lv = array_sum(array_map(fn ($v) => ($v - $lm) ** 2, $lap)) / count($lap);
            if ($lv < (float) config('nis.photo_min_sharpness', 25)) {
                $problems[] = 'The photograph is blurred. Hold the camera still and make sure it is in focus.';
            }
        }

        // Background: the top corners should be plain and light.
        $cw = max(2, (int) ($sw * 0.12));
        $ch = max(2, (int) ($sh * 0.12));
        $bright = 0.0;
        $sat = 0.0;
        $count = 0;
        foreach ([[0, 0], [$sw - $cw, 0]] as [$x0, $y0]) {
            for ($y = $y0; $y < $y0 + $ch; $y++) {
                for ($x = $x0; $x < $x0 + $cw; $x++) {
                    $rgb = imagecolorat($small, $x, $y);
                    $r = ($rgb >> 16) & 255;
                    $g = ($rgb >> 8) & 255;
                    $b = $rgb & 255;
                    $bright += $lum[$y][$x];
                    $sat += max($r, $g, $b) - min($r, $g, $b);
                    $count++;
                }
            }
        }
        imagedestroy($small);
        if ($count > 0 && ($bright / $count < 170 || $sat / $count > 45)) {
            $problems[] = 'Use a plain white or off-white background behind your head.';
        }

        return $problems;
    }
}
