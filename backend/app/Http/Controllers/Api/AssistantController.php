<?php

namespace App\Http\Controllers\Api;

use App\Assistant\Assistant;
use App\Assistant\InputGuard;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat assistant for the public site (guests) and the applicant portal.
 */
class AssistantController
{
    public function guest(Request $request, Assistant $assistant): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($assistant->ask(
            $data['message'],
            $data['conversation_id'] ?? null,
            'guest:'.hash('sha256', (string) $request->ip()),
        ));
    }

    public function applicant(Request $request, Assistant $assistant): JsonResponse
    {
        $data = $this->validated($request);
        /** @var Applicant $applicant */
        $applicant = $request->user();

        return response()->json($assistant->ask(
            $data['message'],
            $data['conversation_id'] ?? null,
            'applicant:'.$applicant->getKey(),
            $applicant,
        ));
    }

    /** @return array{message: string, conversation_id?: string|null} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'message' => ['required', 'string', 'max:'.InputGuard::MAX_LENGTH],
            'conversation_id' => ['nullable', 'uuid'],
        ]);
    }
}
