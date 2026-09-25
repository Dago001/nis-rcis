<?php

namespace App\Models;

use App\Notifications\ApplicantResetPassword;
use App\Notifications\VerifyApplicantEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A member of the public applying for a residence card.
 */
class Applicant extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use \Illuminate\Auth\MustVerifyEmail;

    protected $fillable = [
        'email', 'password', 'surname', 'forenames', 'phone', 'nationality', 'passport_number',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(ResidenceCard::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(ApplicationDraft::class);
    }

    public function fullName(): string
    {
        return trim("{$this->forenames} {$this->surname}");
    }

    public function auditLabel(): string
    {
        return "Applicant {$this->fullName()} <{$this->email}>";
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyApplicantEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ApplicantResetPassword($token));
    }
}
