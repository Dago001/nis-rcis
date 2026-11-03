<?php

namespace App\Models\Scopes;

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentQuery;
use App\Models\CardDistribution;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class RowLevelSecurityScope implements Scope
{
    /**
     * Whether RLS checks are temporarily bypassed for system tasks.
     */
    protected static bool $bypassed = false;

    /**
     * Temporarily execute a callback without RLS restrictions.
     */
    public static function bypass(callable $callback): mixed
    {
        $previous = static::$bypassed;
        static::$bypassed = true;
        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }

    /**
     * Determine if RLS is currently bypassed.
     */
    public static function isBypassed(): bool
    {
        if (static::$bypassed) {
            return true;
        }

        // Running pure console operations like migrations/seeders, but NOT feature tests
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return true;
        }

        return false;
    }

    /**
     * Set explicit bypass status.
     */
    public static function setBypassed(bool $value): void
    {
        static::$bypassed = $value;
    }

    /**
     * Resolve the active authenticated user across all guards.
     */
    public static function resolveUser(): null|User|Applicant
    {
        return Auth::guard('staff')->user()
            ?? Auth::guard('api')->user()
            ?? Auth::guard('applicant-api')->user()
            ?? Auth::guard('applicant')->user()
            ?? Auth::user();
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (static::isBypassed()) {
            return;
        }

        $user = static::resolveUser();

        // 1. APPLICANT CONTEXT: Strict applicant ownership
        if ($user instanceof Applicant) {
            $this->applyApplicantScope($builder, $model, $user);
            return;
        }

        // 2. STAFF CONTEXT: Jurisdictional and role-based restrictions
        if ($user instanceof User) {
            $this->applyStaffScope($builder, $model, $user);
            return;
        }

        // 3. UNAUTHENTICATED / GUEST CONTEXT:
        // Allow public routes that explicitly track or verify via unguessable tokens
        $request = request();
        if ($request && ($request->is('api/v1/public/*') || $request->is('public/*') || $request->is('api/v1/webhooks/*') || $request->is('webhooks/*'))) {
            return;
        }
    }

    /**
     * Apply strict applicant ownership filters.
     */
    protected function applyApplicantScope(Builder $builder, Model $model, Applicant $applicant): void
    {
        if ($model instanceof Application) {
            $builder->where(function (Builder $q) use ($applicant) {
                $q->where('applicant_id', $applicant->id)
                  ->orWhereIn('principal_application_id', function ($sub) use ($applicant) {
                      $sub->select('id')->from('applications')->where('applicant_id', $applicant->id);
                  });
            });
            return;
        }

        if ($model instanceof ResidenceCard) {
            $builder->where('applicant_id', $applicant->id);
            return;
        }

        if ($model instanceof Payment) {
            $builder->where('applicant_id', $applicant->id);
            return;
        }

        if ($model instanceof CardDistribution) {
            $builder->whereHas('application', fn ($q) => $q->where('applicant_id', $applicant->id));
            return;
        }

        if ($model instanceof QueueTicket) {
            $builder->whereHas('application', fn ($q) => $q->where('applicant_id', $applicant->id));
            return;
        }

        if ($model instanceof ApplicationDocument) {
            $builder->where(function (Builder $q) use ($applicant) {
                $q->whereHas('application', fn ($aq) => $aq->where('applicant_id', $applicant->id))
                  ->orWhereHas('draft', fn ($dq) => $dq->where('applicant_id', $applicant->id));
            });
            return;
        }

        if ($model instanceof ApplicationDocumentQuery) {
            $builder->whereHas('application', fn ($aq) => $aq->where('applicant_id', $applicant->id));
            return;
        }
    }

    /**
     * Apply staff jurisdictional and role-based filters.
     */
    protected function applyStaffScope(Builder $builder, Model $model, User $user): void
    {
        // Headquarters administrators and Super Admins have system-wide access
        if ($user->isHeadquarters()) {
            return;
        }

        // ZONAL JURISDICTION
        if ($user->isZonal()) {
            $this->applyZonalScope($builder, $model, $user);
            return;
        }

        // COMMAND JURISDICTION
        if ($user->isCommand()) {
            $this->applyCommandScope($builder, $model, $user);
            return;
        }

        // STATE FALLBACK JURISDICTION
        if ($user->state) {
            if ($model instanceof Application) {
                $builder->where(function (Builder $q) use ($user) {
                    $q->whereHas('enrollmentCenter', fn ($cq) => $cq->where('state', $user->state))
                      ->orWhere('domicile_state', $user->state);
                });
            }
        }
    }

    /**
     * Scope records to the officer's assigned Zone.
     */
    protected function applyZonalScope(Builder $builder, Model $model, User $user): void
    {
        if ($model instanceof Application) {
            $builder->where(function (Builder $q) use ($user) {
                $q->whereHas('enrollmentCenter', fn ($cq) => $cq->where('zone_id', $user->zone_id))
                  ->orWhereHas('actualEnrollmentCenter', fn ($cq) => $cq->where('zone_id', $user->zone_id))
                  ->orWhereHas('cardDistribution', fn ($dq) => $dq->where('destination_zone_id', $user->zone_id));
                if ($user->zone) {
                    $q->orWhere('actual_enrollment_zone', $user->zone->name)
                      ->orWhere('actual_enrollment_zone', $user->zone->code);
                }
            });
            return;
        }

        if ($model instanceof ResidenceCard) {
            $builder->where(function (Builder $q) use ($user) {
                $q->whereHas('application.enrollmentCenter', fn ($cq) => $cq->where('zone_id', $user->zone_id))
                  ->orWhereHas('actualEnrollmentCenter', fn ($cq) => $cq->where('zone_id', $user->zone_id))
                  ->orWhereHas('distribution', fn ($dq) => $dq->where('destination_zone_id', $user->zone_id));
                if ($user->zone) {
                    $q->orWhere('enrollment_zone', $user->zone->name);
                }
            });
            return;
        }

        if ($model instanceof CardDistribution) {
            $builder->where('destination_zone_id', $user->zone_id);
            return;
        }

        if ($model instanceof QueueTicket) {
            $builder->whereHas('enrollmentCenter', fn ($cq) => $cq->where('zone_id', $user->zone_id));
            return;
        }

        if ($model instanceof Payment) {
            $builder->whereHas('application', fn ($aq) => $aq->forStaffJurisdiction($user));
            return;
        }

        if ($model instanceof ApplicationDocument || $model instanceof ApplicationDocumentQuery) {
            $builder->whereHas('application', fn ($aq) => $aq->forStaffJurisdiction($user));
            return;
        }
    }

    /**
     * Scope records to the officer's assigned Command.
     */
    protected function applyCommandScope(Builder $builder, Model $model, User $user): void
    {
        if ($model instanceof Application) {
            $builder->where(function (Builder $q) use ($user) {
                $q->whereHas('enrollmentCenter', fn ($cq) => $cq->where('command_id', $user->command_id))
                  ->orWhereHas('actualEnrollmentCenter', fn ($cq) => $cq->where('command_id', $user->command_id))
                  ->orWhereHas('cardDistribution', fn ($dq) => $dq->where('destination_command_id', $user->command_id));
                if ($user->nisCommand) {
                    $q->orWhere('actual_enrollment_command', $user->nisCommand->name)
                      ->orWhere('actual_enrollment_command', $user->nisCommand->code);
                }
            });
            return;
        }

        if ($model instanceof ResidenceCard) {
            $builder->where(function (Builder $q) use ($user) {
                $q->whereHas('application.enrollmentCenter', fn ($cq) => $cq->where('command_id', $user->command_id))
                  ->orWhereHas('actualEnrollmentCenter', fn ($cq) => $cq->where('command_id', $user->command_id))
                  ->orWhereHas('distribution', fn ($dq) => $dq->where('destination_command_id', $user->command_id));
                if ($user->nisCommand) {
                    $q->orWhere('enrollment_command', $user->nisCommand->name);
                }
            });
            return;
        }

        if ($model instanceof CardDistribution) {
            $builder->where('destination_command_id', $user->command_id);
            return;
        }

        if ($model instanceof QueueTicket) {
            $builder->whereHas('enrollmentCenter', fn ($cq) => $cq->where('command_id', $user->command_id));
            return;
        }

        if ($model instanceof Payment) {
            $builder->whereHas('application', fn ($aq) => $aq->forStaffJurisdiction($user));
            return;
        }

        if ($model instanceof ApplicationDocument || $model instanceof ApplicationDocumentQuery) {
            $builder->whereHas('application', fn ($aq) => $aq->forStaffJurisdiction($user));
            return;
        }
    }
}
