<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\Application;
use App\Models\EnrollmentCenter;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use App\Services\AppointmentScheduler;
use App\Services\DocumentStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated, rate-limited endpoints.
 */
class PublicController
{
    public function centers(): JsonResponse
    {
        return response()->json([
            'data' => EnrollmentCenter::where('is_active', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'state', 'address', 'time_slots']),
            'fee_naira' => config('nis.fee_naira'),
        ]);
    }

    /**
     * "Now serving" screen for an enrollment centre's waiting room. Ticket
     * numbers and desks only: never names.
     */
    public function queueDisplay(EnrollmentCenter $center): JsonResponse
    {
        abort_unless($center->is_active, 404);
        $today = QueueTicket::where('enrollment_center_id', $center->id)->whereDate('service_date', today());

        return response()->json([
            'center' => $center->name,
            'serving' => (clone $today)->where('status', 'CALLED')->latest('called_at')->limit(8)->get(['ticket_number', 'desk', 'called_at']),
            'waiting' => (clone $today)->where('status', 'WAITING')->count(),
            'next' => (clone $today)->where('status', 'WAITING')->orderBy('checked_in_at')->limit(5)->pluck('ticket_number'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function availability(Request $request, EnrollmentCenter $center, AppointmentScheduler $scheduler): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date', 'after:today', 'before:+60 days']]);
        $date = CarbonImmutable::parse($data['date']);

        return response()->json([
            'date' => $date->toDateString(),
            'weekend' => $date->isWeekend(),
            'slots' => $date->isWeekend() ? [] : $scheduler->availability($center, $date),
        ]);
    }

    /**
     * Public tracking. Requires BOTH the application (or reference) number
     * and the passport number, and reveals no personal particulars.
     */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:32'],
            'passport_number' => ['required', 'string', 'max:20'],
        ]);

        $number = strtoupper(trim($data['application_number']));
        $application = Application::query()
            ->where(fn ($q) => $q->where('application_number', $number)->orWhere('reference_number', $number))
            ->where('passport_number', strtoupper(trim($data['passport_number'])))
            ->with('enrollmentCenter')
            ->first();

        abort_unless($application, 404, 'No application matches the details supplied.');

        return response()->json([
            'application_number' => $application->application_number,
            'holder' => $this->mask($application->surname).' '.$this->mask($application->forenames),
            'type' => $application->type,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'tracker_step' => $application->status->trackerStep(),
            'submitted_at' => $application->submitted_at?->toDateString(),
            'appointment_date' => $application->appointment_date?->toDateString(),
            'appointment_time' => $application->appointment_time,
            'enrollment_center' => $application->enrollmentCenter?->name,
        ]);
    }

    /**
     * Card verification.
     *  - by QR token (printed on the card): holder details + photo
     *  - by card number + passport number: status and holder name only
     */
    public function verifyCard(Request $request, DocumentStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required_without_all:card_number,passport_number', 'nullable', 'string', 'size:64'],
            'card_number' => ['required_without:token', 'nullable', 'string', 'max:20'],
            'passport_number' => ['required_with:card_number', 'nullable', 'string', 'max:20'],
        ]);

        $byToken = ! empty($data['token']);
        $card = $byToken
            ? ResidenceCard::where('verification_token', $data['token'])->first()
            : ResidenceCard::where('card_number', trim($data['card_number']))
                ->where('passport_number', strtoupper(trim($data['passport_number'])))->first();

        abort_unless($card, 404, 'No residence card matches the details supplied.');

        $status = $card->verificationStatus();

        return response()->json([
            // Watchlist details are only disclosed to partner agencies.
            'status' => $status === 'WATCHLISTED' ? 'REFER_TO_NIS' : $status,
            'card_number' => $card->card_number,
            'holder' => "{$card->surname}, {$card->forenames}",
            'nationality' => $card->nationality,
            'expires_on' => $card->expires_on?->toDateString(),
            'issued_on' => $card->issued_on?->toDateString(),
            'photo_url' => $byToken ? $storage->temporaryUrlForPath($card->photo_path) : null,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function mask(string $name): string
    {
        return collect(explode(' ', $name))
            ->map(fn ($part) => mb_substr($part, 0, 1).str_repeat('*', max(0, mb_strlen($part) - 1)))
            ->implode(' ');
    }
}
