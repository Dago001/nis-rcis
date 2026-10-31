<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\EnrollmentCenter;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Biometrics appointment capacity per enrollment center and time slot.
 */
class AppointmentScheduler
{
    public function slotCapacity(EnrollmentCenter $center): int
    {
        return max(1, intdiv($center->daily_capacity, max(1, count($center->time_slots))));
    }

    /** @return list<array{time: string, remaining: int}> */
    public function availability(EnrollmentCenter $center, CarbonImmutable $date): array
    {
        $booked = Application::query()
            ->where('enrollment_center_id', $center->id)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Issued])
            ->selectRaw('appointment_time, count(*) as total')
            ->groupBy('appointment_time')
            ->pluck('total', 'appointment_time');

        $capacity = $this->slotCapacity($center);

        return collect($center->time_slots)
            ->map(fn (string $time) => ['time' => $time, 'remaining' => max(0, $capacity - (int) ($booked[$time] ?? 0))])
            ->values()
            ->all();
    }

    /**
     * Must be called inside the transaction that creates the application.
     */
    public function assertBookable(EnrollmentCenter $center, string $date, string $time, mixed $paymentDate = null): void
    {
        $day = CarbonImmutable::parse($date)->setTimezone('Africa/Lagos')->startOfDay();

        if ($paymentDate !== null) {
            $paidAt = $paymentDate instanceof \App\Models\Payment
                ? ($paymentDate->paid_at ?: $paymentDate->created_at ?: now())
                : $paymentDate;
            $paymentDayInLagos = CarbonImmutable::parse($paidAt)->setTimezone('Africa/Lagos')->startOfDay();
            $earliestEligible = $paymentDayInLagos->addDays(4);

            if ($day->lt($earliestEligible)) {
                throw ValidationException::withMessages([
                    'appointment_date' => "Appointment booking is only permitted from the fourth calendar day after payment ({$earliestEligible->format('d/m/Y')}) onward. Dates prior to this are not selectable.",
                ]);
            }
        }

        if ($day->isWeekend()) {
            throw ValidationException::withMessages(['appointment_date' => 'Enrollment centers do not capture biometrics at weekends.']);
        }

        if (! in_array($time, $center->time_slots, true)) {
            throw ValidationException::withMessages(['appointment_time' => 'Select one of the center\'s available time slots.']);
        }

        // Serialise bookings for this center to prevent overbooking races.
        EnrollmentCenter::whereKey($center->id)->lockForUpdate()->first();

        $slot = collect($this->availability($center, $day))->firstWhere('time', $time);
        if (($slot['remaining'] ?? 0) < 1) {
            throw ValidationException::withMessages(['appointment_time' => 'This time slot is fully booked. Please choose another.']);
        }
    }
}
