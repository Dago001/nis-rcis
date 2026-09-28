<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\DataBreach;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Personal-data breach register (NDPA 2023). Super Administrators record
 * and update; Auditors can read.
 */
class DataBreachController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DataBreach::with('reporter:id,fullname,service_number')->latest('detected_at')->get()->map(fn ($b) => $this->present($b))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $breach = DataBreach::create([...$data, 'status' => $data['status'] ?? 'OPEN', 'reported_by' => $request->user()->id]);
        Audit::log('DATA_BREACH_RECORDED', "Data breach recorded: {$breach->title}", $breach, ['severity' => $breach->severity]);

        return response()->json(['data' => $this->present($breach->load('reporter'))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $breach = DataBreach::findOrFail($id);
        $breach->fill($request->validate($this->rules(false)))->save();
        Audit::log('DATA_BREACH_UPDATED', "Data breach updated: {$breach->title}", $breach, ['changes' => array_keys($breach->getChanges())]);

        return response()->json(['data' => $this->present($breach->load('reporter'))]);
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'string', 'max:200'],
            'description' => [$req, 'string', 'max:5000'],
            'severity' => [$req, Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'status' => ['sometimes', Rule::in(['OPEN', 'CONTAINED', 'CLOSED'])],
            'occurred_at' => ['nullable', 'date'],
            'detected_at' => [$req, 'date', 'before_or_equal:now'],
            'data_categories' => ['nullable', 'string', 'max:300'],
            'affected_count' => ['nullable', 'integer', 'min:0'],
            'containment_actions' => ['nullable', 'string', 'max:5000'],
            'regulator_notified_at' => ['nullable', 'date'],
            'subjects_notified_at' => ['nullable', 'date'],
        ];
    }

    private function present(DataBreach $b): array
    {
        $deadline = $b->detected_at?->copy()->addHours(72);

        return [
            ...$b->only(['id', 'title', 'description', 'severity', 'status', 'data_categories', 'affected_count', 'containment_actions']),
            'occurred_at' => $b->occurred_at?->toIso8601String(),
            'detected_at' => $b->detected_at?->toIso8601String(),
            'regulator_notified_at' => $b->regulator_notified_at?->toIso8601String(),
            'subjects_notified_at' => $b->subjects_notified_at?->toIso8601String(),
            'regulator_deadline' => $deadline?->toIso8601String(),
            'regulator_overdue' => $b->regulator_notified_at === null && $deadline !== null && $deadline->isPast(),
            'reported_by' => $b->reporter ? "{$b->reporter->fullname} ({$b->reporter->service_number})" : null,
        ];
    }
}
