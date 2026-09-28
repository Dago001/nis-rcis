<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Notifications\ApplicationAwaitingReview;
use App\Notifications\ApplicationStatusChanged;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The single place where an application's status changes.
 *
 * Every transition is validated against ApplicationStatus::allowedTransitions(),
 * performed under a row lock, recorded in application_status_histories and
 * the audit log, and the applicant is notified (e-mail + in-portal).
 */
class ApplicationWorkflow
{
    /**
     * @param  callable(Application): void|null  $mutate  extra column changes made in the same transaction
     */
    public function transition(
        Application $application,
        ApplicationStatus $to,
        Model $actor,
        ?string $notes = null,
        ?callable $mutate = null,
    ): Application {
        $application = DB::transaction(function () use ($application, $to, $actor, $notes, $mutate) {
            /** @var Application $locked */
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Application {$locked->application_number} is {$from->value}; it cannot move to {$to->value}.",
                ]);
            }

            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();

            $history = new ApplicationStatusHistory([
                'application_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'notes' => $notes,
            ]);
            $history->actor()->associate($actor);
            $history->save();

            Audit::log("APPLICATION_{$to->value}", "Application {$locked->application_number}: {$from->value} → {$to->value}", $locked,
                array_filter(['notes' => $notes]), $actor);

            return $locked;
        });

        $this->notify($application, $notes);

        return $application;
    }

    /**
     * Record the initial PENDING_APPROVAL state of a newly created application.
     */
    public function recordSubmission(Application $application, Model $actor): void
    {
        $history = new ApplicationStatusHistory([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::PendingApproval,
            'notes' => $application->channel === 'ASSISTED' ? 'Assisted application entered by staff' : 'Submitted online',
        ]);
        $history->actor()->associate($actor);
        $history->save();

        Audit::log('APPLICATION_SUBMITTED', "Application {$application->application_number} submitted ({$application->channel})", $application, actor: $actor);

        $this->notify($application, null);
    }

    private function notify(Application $application, ?string $notes): void
    {
        if ($application->applicant) {
            $application->applicant->notify(new ApplicationStatusChanged($application, $notes));
        }

        if ($application->status === ApplicationStatus::PendingApproval) {
            $reviewers = User::query()
                ->where('is_active', true)
                ->whereIn('role', [StaffRole::ApprovingOfficer, StaffRole::SuperAdmin])
                ->get();
            Notification::send($reviewers, new ApplicationAwaitingReview($application));
        }
    }
}
