<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApplicationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['application_id', 'from_status', 'to_status', 'notes'];

    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
