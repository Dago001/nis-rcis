<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Models\Application;
use App\Models\ResidenceCard;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'service_number' => $user->service_number,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'command' => $user->command,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $applications = Application::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $cards = ResidenceCard::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'queue' => collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($applications[$s->value] ?? 0)]),
            'cards' => collect(CardStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($cards[$s->value] ?? 0)]),
            'cards_expired' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->where('expires_on', '<', today())->count(),
            'cards_expiring_30_days' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereBetween('expires_on', [today(), today()->addDays(30)])->count(),
            'watchlisted' => ResidenceCard::where('is_watchlisted', true)->count(),
            'todays_appointments' => Application::where('status', ApplicationStatus::ApprovedForBiometrics)->whereDate('appointment_date', today())->count(),
            'by_nationality' => ResidenceCard::selectRaw('nationality, count(*) as total')->groupBy('nationality')->orderByDesc('total')->limit(6)->get(),
            'my_activity' => [
                'decided' => Application::where('decided_by', $user->id)->count(),
                'captured' => Application::where('biometrics_captured_by', $user->id)->count(),
                'cards_approved' => ResidenceCard::where('approved_by', $user->id)->count(),
            ],
        ]);
    }
}
