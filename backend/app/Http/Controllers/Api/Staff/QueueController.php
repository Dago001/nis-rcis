<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\EnrollmentCenter;
use App\Models\QueueTicket;
use App\Services\CentreQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Enrollment-centre queue desk (check-in, walk-ins, calling and closing tickets).
 */
class QueueController
{
    public function index(Request $request): JsonResponse
    {
        $center = $this->center($request);
        $tickets = QueueTicket::with('application:id,application_number,status')
            ->where('enrollment_center_id', $center->id)->whereDate('service_date', today())
            ->orderBy('checked_in_at')->get();

        $centersQuery = EnrollmentCenter::where('is_active', true)->orderBy('name');
        if ($request->user() && ! $request->user()->isHeadquarters()) {
            if ($request->user()->isCommand()) {
                $centersQuery->where('command_id', $request->user()->command_id);
            } elseif ($request->user()->isZonal()) {
                $centersQuery->where('zone_id', $request->user()->zone_id);
            }
        }

        return response()->json([
            'center' => $center->only(['id', 'code', 'name']),
            'centers' => $centersQuery->get(['id', 'code', 'name']),
            'data' => $tickets->map(fn (QueueTicket $t) => $this->present($t)),
            'stats' => [
                'waiting' => $tickets->where('status', CentreQueue::WAITING)->count(),
                'called' => $tickets->where('status', CentreQueue::CALLED)->count(),
                'done' => $tickets->where('status', CentreQueue::DONE)->count(),
                'no_show' => $tickets->where('status', CentreQueue::NO_SHOW)->count(),
                'walk_ins' => $tickets->where('kind', 'WALK_IN')->count(),
            ],
        ]);
    }

    public function checkIn(Request $request, CentreQueue $queue): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:120']]);
        $result = $queue->checkIn($this->center($request), $request->user(), $data['code']);

        return response()->json(['data' => $this->present($result['ticket']), 'warning' => $result['warning'], 'existing' => $result['existing']], $result['existing'] ? 200 : 201);
    }

    public function walkIn(Request $request, CentreQueue $queue): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'max:150'],
        ]);

        return response()->json(['data' => $this->present($queue->walkIn($this->center($request), $request->user(), $data['name'], $data['purpose']))], 201);
    }

    public function call(Request $request, CentreQueue $queue): JsonResponse
    {
        $data = $request->validate([
            'desk' => ['required', 'string', 'max:20'],
            'ticket_id' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => $this->present($queue->call($this->center($request), $request->user(), $data['desk'], $data['ticket_id'] ?? null))]);
    }

    public function finish(Request $request, int $id, CentreQueue $queue): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([CentreQueue::DONE, CentreQueue::NO_SHOW])]]);
        $ticket = QueueTicket::with('enrollmentCenter')->findOrFail($id);

        if ($request->user() && ! $request->user()->isHeadquarters()) {
            if ($request->user()->isCommand() && (int) $ticket->enrollmentCenter?->command_id !== (int) $request->user()->command_id) {
                abort(403, 'You do not have jurisdiction over this queue ticket.');
            }
            if ($request->user()->isZonal() && (int) $ticket->enrollmentCenter?->zone_id !== (int) $request->user()->zone_id) {
                abort(403, 'You do not have jurisdiction over this queue ticket.');
            }
        }

        return response()->json(['data' => $this->present($queue->finish($ticket, $request->user(), $data['status']))]);
    }

    private function center(Request $request): EnrollmentCenter
    {
        $user = $request->user();
        $id = $request->integer('enrollment_center_id');

        $query = EnrollmentCenter::where('is_active', true);
        if ($user && ! $user->isHeadquarters()) {
            if ($user->isCommand()) {
                $query->where('command_id', $user->command_id);
            } elseif ($user->isZonal()) {
                $query->where('zone_id', $user->zone_id);
            }
        }

        if ($id) {
            $center = EnrollmentCenter::where('is_active', true)->findOrFail($id);
            if ($user && ! $user->isHeadquarters()) {
                if ($user->isCommand() && (int) $center->command_id !== (int) $user->command_id) {
                    abort(403, 'You do not have jurisdiction to operate queues at this enrollment center.');
                }
                if ($user->isZonal() && (int) $center->zone_id !== (int) $user->zone_id) {
                    abort(403, 'You do not have jurisdiction to operate queues at this enrollment center.');
                }
            }
            return $center;
        }

        $center = $query->orderBy('id')->first();
        if (! $center) {
            abort(403, 'No active enrollment center available for your assigned jurisdiction.');
        }

        return $center;
    }

    private function present(QueueTicket $t): array
    {
        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'kind' => $t->kind,
            'name' => $t->name,
            'purpose' => $t->purpose,
            'status' => $t->status,
            'desk' => $t->desk,
            'application' => $t->application ? ['id' => $t->application->id, 'application_number' => $t->application->application_number, 'status' => $t->application->status->value] : null,
            'checked_in_at' => $t->checked_in_at?->toIso8601String(),
            'called_at' => $t->called_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'wait_minutes' => (int) $t->checked_in_at?->diffInMinutes($t->called_at ?? now()),
        ];
    }
}
