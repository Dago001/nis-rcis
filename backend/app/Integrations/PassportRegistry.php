<?php

namespace App\Integrations;

/** Interpol Stolen and Lost Travel Documents (SLTD), through the NCB Abuja connection. */
interface PassportRegistry
{
    public function check(string $passportNumber, string $nationality): CheckResult;
}
