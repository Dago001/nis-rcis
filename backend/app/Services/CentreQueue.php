<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\EnrollmentCenter;
use App\Models\QueueTicket;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrollment-centre queue: applicants check in by scanning the barcode on
 * their slip (or walk in), officers call the next ticket to a desk, and a
 * public screen shows who is being served.
 */
class CentreQueue
{
    public const WAITING = 'WAITING';

    public const CALLED = 'CALLED';

    public const DONE = 'DONE';

    public const NO_SHOW = 'NO_SHOW';

    /**
     * Check in with the code on a slip: the application number (application
     * slip barcode), the reference number (appointment slip barcode) or the
     * QR code text.
     *
     * @return array{ticket: QueueTicket, warning: ?string, existing: bool}
     */
    public function checkIn(EnrollmentCenter $center, User $officer, string $code): array
    {
        $application = $this->findByCode($code)
            ?? throw ValidationException::withMessages(['code' => 'No application matches this barcode. Check the slip, or add the person as a walk-in.']);

        $purpose = match ($application->status) {
            ApplicationStatus::ApprovedForBiometrics => 'Biometrics capture',
            ApplicationStatus::ReadyForCollection => 'Card collection',
            default => throw ValidationException::withMessages([
                'code' => "Application {$application->application_number} is {$application->status->label()}: it has no biometrics appointment or card to collect.",
            ]),
        };

        $existing = QueueTicket::where('application_id', $application->id)->whereDate('service_date', today())
            ->whereIn('status', [self::WAITING, self::CALLED])->first();
        if ($existing) {
            return ['ticket' => $existing, 'warning' => 'Already checked in today.', 'existing' => true];
        }

        $warning = null;
        if ($purpose === 'Biometrics capture' && ! $application->appointment_date?->isToday()) {
            $warning = 'The appointment is on '.$application->appointment_date?->format('d M Y').', not today.';
        }
        if ($application->enrollment_center_id && $application->enrollment_center_id !== $center->id) {
            $warning = trim(($warning ?? '').' The appointment was booked at another centre.');
        }

        $ticket = $this->issue($center, $officer, 'APPOINTMENT', [
            'application_id' => $application->id,
            'name' => "{$application->surname}, {$application->forenames}",
            'purpose' => $purpose,
        ]);

        return ['ticket' => $ticket, 'warning' => $warning, 'existing' => false];
    }

    public function walkIn(EnrollmentCenter $center, User $officer, string $name, string $purpose): QueueTicket
    {
        return $this->issue($center, $officer, 'WALK_IN', ['name' => mb_strtoupper($name), 'purpose' => $purpose]);
    }

    /** Call a ticket (or the longest-waiting one) to a desk. */
    public function call(EnrollmentCenter $center, User $officer, string $desk, ?int $ticketId = null): QueueTicket
    {
        return DB::transaction(function () use ($center, $officer, $desk, $ticketId) {
            $query = QueueTicket::where('enrollment_center_id', $center->id)->whereDate('service_date', today())->lockForUpdate();
            $ticket = $ticketId
                ? $query->whereKey($ticketId)->whereIn('status', [self::WAITING, self::CALLED])->first()
                : $query->where('status', self::WAITING)->orderBy('checked_in_at')->first();

            if (! $ticket) {
                throw ValidationException::withMessages(['ticket' => $ticketId ? 'This ticket can no longer be called.' : 'Nobody is waiting.']);
            }

            $ticket->update(['status' => self::CALLED, 'desk' => $desk, 'called_at' => now(), 'served_by' => $officer->id]);

            return $ticket;
        });
    }

    public function finish(QueueTicket $ticket, User $officer, string $status): QueueTicket
    {
        if (! in_array($ticket->status, [self::WAITING, self::CALLED], true)) {
            throw ValidationException::withMessages(['ticket' => 'This ticket is already closed.']);
        }
        $ticket->update(['status' => $status, 'completed_at' => now(), 'served_by' => $ticket->served_by ?? $officer->id]);

        return $ticket;
    }

    public function findByCode(string $code): ?Application
    {
        $code = strtoupper(trim($code));
        // QR text on the slip: NIS-RCIS|APP:RC-2026-000001|REF:NIS-XXXX-XXXX-XXXX
        if (preg_match('/APP:([A-Z0-9-]+)/', $code, $m)) {
            $code = $m[1];
        }
        $compact = preg_replace('/[^A-Z0-9]/', '', $code);

        return Application::where('application_number', $code)->orWhere('reference_number', $code)
            ->orWhereRaw("replace(application_number, '-', '') = ?", [$compact])
            ->orWhereRaw("replace(reference_number, '-', '') = ?", [$compact])
            ->first();
    }

    private function issue(EnrollmentCenter $center, User $officer, string $kind, array $attributes): QueueTicket
    {
        return DB::transaction(function () use ($center, $officer, $kind, $attributes) {
            // One numbering sequence per centre, day and kind.
            EnrollmentCenter::whereKey($center->id)->lockForUpdate()->first();
            $prefix = $kind === 'WALK_IN' ? 'W' : 'A';
            $count = QueueTicket::where('enrollment_center_id', $center->id)->whereDate('service_date', today())->where('kind', $kind)->count();

            $ticket = QueueTicket::create([
                ...$attributes,
                'enrollment_center_id' => $center->id,
                'service_date' => today(),
                'ticket_number' => $prefix.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT),
                'kind' => $kind,
                'status' => self::WAITING,
                'checked_in_at' => now(),
                'checked_in_by' => $officer->id,
            ]);
            Audit::log('QUEUE_CHECK_IN', "Ticket {$ticket->ticket_number} ({$kind}) issued at {$center->name}", $ticket, actor: $officer);

            return $ticket;
        });
    }
}
