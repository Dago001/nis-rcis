<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\PushSubscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SettingController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $broadcast = SystemSetting::get('broadcast', [
            'is_active' => false,
            'title' => '',
            'message' => '',
            'tone' => 'info',
            'target' => 'all',
            'expires_at' => null,
            'updated_at' => null,
        ]);

        $countdown = SystemSetting::get('countdown', [
            'is_active' => false,
            'title' => '',
            'target_date' => null,
            'description' => '',
            'link_label' => '',
            'link_url' => '',
            'updated_at' => null,
        ]);

        $stats = [
            'push_subscribers' => PushSubscription::count(),
            'total_applicants' => Applicant::count(),
            'total_staff' => User::where('is_active', true)->count(),
        ];

        return response()->json([
            'broadcast' => $broadcast,
            'countdown' => $countdown,
            'stats' => $stats,
            'general' => [
                'portal_name' => config('app.name', 'Nigeria Immigration Service - ECOWAS Residence Card Portal'),
                'organization' => config('nis.organization', 'Nigeria Immigration Service'),
                'directorate' => config('nis.directorate', 'Directorate of Visa and Residency'),
                'authority' => config('nis.approving_authority', 'COMPTROLLER GENERAL OF IMMIGRATION'),
                'timezone' => config('app.timezone', 'Africa/Lagos'),
                'environment' => config('nis.environment_label', 'STAGING'),
            ],
            'workflow' => [
                'card_validity_years' => config('nis.card_validity_years', 2),
                'temporary_card_validity_days' => config('nis.temporary_card_validity_days', 30),
                'sla_working_days' => config('nis.sla_working_days', 10),
                'fee_naira' => config('nis.fee_naira', 35000),
                'cod_fee_naira' => config('nis.cod_fee_naira', 15000),
            ],
            'security' => [
                'two_factor_required' => true,
                'geofencing_enabled' => config('nis.geofencing_enabled', true),
                'geofencing_strict' => config('nis.geofencing_strict', false),
            ],
            'notifications' => [
                'alert_emails' => config('nis.alerts.emails', []),
            ],
            'user_can_edit' => $user && in_array($user->role?->value, ['SuperAdmin', 'Admin', 'HeadquartersAdmin'], true),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role?->value, ['SuperAdmin', 'Admin', 'HeadquartersAdmin'], true)) {
            return response()->json(['message' => 'Unauthorized. Only Super Administrators and Headquarters Administrators may modify settings.'], 403);
        }
        $data = $request->validate([
            'broadcast' => ['nullable', 'array'],
            'broadcast.is_active' => ['nullable', 'boolean'],
            'broadcast.title' => ['nullable', 'string', 'max:150'],
            'broadcast.message' => ['nullable', 'string', 'max:1000'],
            'broadcast.tone' => ['nullable', 'in:info,warning,alert,success'],
            'broadcast.target' => ['nullable', 'in:all,public,applicants,staff'],
            'broadcast.expires_at' => ['nullable', 'string'],

            'countdown' => ['nullable', 'array'],
            'countdown.is_active' => ['nullable', 'boolean'],
            'countdown.title' => ['nullable', 'string', 'max:150'],
            'countdown.target_date' => ['nullable', 'string'],
            'countdown.description' => ['nullable', 'string', 'max:500'],
            'countdown.link_label' => ['nullable', 'string', 'max:100'],
            'countdown.link_url' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['broadcast'])) {
            $broadcast = array_merge(
                SystemSetting::get('broadcast', []),
                $data['broadcast'],
                ['updated_at' => now()->toIso8601String()]
            );
            SystemSetting::set('broadcast', $broadcast);
        }

        if (isset($data['countdown'])) {
            $countdown = array_merge(
                SystemSetting::get('countdown', []),
                $data['countdown'],
                ['updated_at' => now()->toIso8601String()]
            );
            SystemSetting::set('countdown', $countdown);
        }

        Audit::log('SETTINGS_UPDATED', 'Updated broadcast notifications and countdown settings', actor: $request->user());

        return $this->index($request);
    }

    public function pushBroadcast(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role?->value, ['SuperAdmin', 'Admin', 'HeadquartersAdmin'], true)) {
            return response()->json(['message' => 'Unauthorized. Only Super Administrators and Headquarters Administrators may push notifications.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'tone' => ['nullable', 'in:info,warning,alert,success'],
            'target' => ['nullable', 'in:all,applicants,staff'],
            'url' => ['nullable', 'string', 'max:255'],
        ]);

        $tone = $data['tone'] ?? 'info';
        $target = $data['target'] ?? 'all';
        $notification = new SystemBroadcastNotification(
            title: $data['title'],
            message: $data['message'],
            tone: $tone,
            url: $data['url'] ?? null
        );

        $recipientsCount = 0;

        if ($target === 'all' || $target === 'applicants') {
            $applicants = Applicant::cursor();
            foreach ($applicants as $applicant) {
                $applicant->notify($notification);
                $recipientsCount++;
            }
        }

        if ($target === 'all' || $target === 'staff') {
            $staff = User::where('is_active', true)->cursor();
            foreach ($staff as $user) {
                $user->notify($notification);
                $recipientsCount++;
            }
        }

        Audit::log(
            'BROADCAST_NOTIFICATION_PUSHED',
            "Pushed broadcast notification '{$data['title']}' to {$recipientsCount} recipient(s) (target: {$target})",
            actor: $request->user()
        );

        return response()->json([
            'success' => true,
            'recipients_count' => $recipientsCount,
            'message' => "Successfully pushed notification to {$recipientsCount} recipient(s).",
        ]);
    }

    public function publicAnnouncements(): JsonResponse
    {
        $broadcast = SystemSetting::get('broadcast', ['is_active' => false]);
        $countdown = SystemSetting::get('countdown', ['is_active' => false]);

        // Auto-expire broadcast if past expiration date
        if (! empty($broadcast['is_active']) && ! empty($broadcast['expires_at'])) {
            if (strtotime($broadcast['expires_at']) <= time()) {
                $broadcast['is_active'] = false;
            }
        }

        // Auto-expire countdown if past target date
        if (! empty($countdown['is_active']) && ! empty($countdown['target_date'])) {
            if (strtotime($countdown['target_date']) <= time()) {
                $countdown['is_active'] = false;
            }
        }

        return response()->json([
            'broadcast' => $broadcast,
            'countdown' => $countdown,
        ]);
    }
}
