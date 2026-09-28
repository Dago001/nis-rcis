<?php

namespace App\Rules;

use App\Support\NigeriaLgas;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a local government area of the state given in another field.
 */
class NigerianLga implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private readonly string $stateField) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $state = $this->data[$this->stateField] ?? null;
        if (! is_string($value) || ! is_string($state) || ! in_array($value, NigeriaLgas::lgas($state), true)) {
            $fail('Select a local government area of the chosen state.');
        }
    }
}
