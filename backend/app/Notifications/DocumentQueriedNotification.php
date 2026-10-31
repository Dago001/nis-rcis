<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationDocumentQuery;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentQueriedNotification extends Notification
{
    use PushesToDevices, Queueable;

    /**
     * @param Application $application
     * @param list<ApplicationDocumentQuery>|ApplicationDocumentQuery $queries
     */
    public function __construct(
        public Application $application,
        public mixed $queries
    ) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, ['database', 'mail']);
    }

    /**
     * @return list<ApplicationDocumentQuery>
     */
    protected function getQueriesList(): array
    {
        if (is_array($this->queries)) {
            return $this->queries;
        }

        if ($this->queries instanceof ApplicationDocumentQuery) {
            return [$this->queries];
        }

        if ($this->queries instanceof \Illuminate\Support\Collection) {
            return $this->queries->all();
        }

        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = $this->application;
        $url = config('nis.frontend_url') . "/portal/applications/{$app->id}";
        $queries = $this->getQueriesList();

        $mail = (new MailMessage)
            ->subject("ACTION REQUIRED: Supporting Document Query on Application {$app->application_number}")
            ->greeting("Dear {$app->forenames} {$app->surname},")
            ->line("An immigration officer has reviewed your ECOWAS Residence Card application ({$app->application_number}) and issued a query regarding your submitted supporting document(s).")
            ->line("**Application Reference:** {$app->reference_number}")
            ->line("**Application Number:** {$app->application_number}");

        foreach ($queries as $q) {
            $docLabel = $q->document?->type?->label() ?? $q->document_type;
            $mail->line("---")
                ->line("**Queried Document:** {$docLabel}")
                ->line("**Reason for Query:** {$q->query_reason}")
                ->line("**Query Date:** " . ($q->queried_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')))
                ->line("**Review Stage:** " . ucfirst(strtolower($q->stage)));
        }

        $mail->line("---")
            ->line("**Required Action:** Please log in to your applicant portal, review the specific query instructions for each document, and upload a clear, valid replacement document to continue processing.")
            ->action('Resolve Document Query', $url)
            ->line("Please note: Your application cannot proceed to the next stage until the queried documents are replaced and verified.");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $app = $this->application;
        $queries = $this->getQueriesList();

        $items = array_map(function (ApplicationDocumentQuery $q) {
            return [
                'query_id' => $q->id,
                'document_id' => $q->document_id,
                'document_type' => $q->document_type,
                'document_label' => $q->document?->type?->label() ?? $q->document_type,
                'reason' => $q->query_reason,
                'stage' => $q->stage,
                'date' => $q->queried_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        }, $queries);

        $firstDocLabel = $items[0]['document_label'] ?? 'Supporting Document';
        $title = count($items) > 1
            ? count($items) . ' Supporting Documents Queried'
            : "Query on {$firstDocLabel}";

        return [
            'type' => 'DOCUMENT_QUERIED',
            'title' => $title,
            'application_id' => $app->id,
            'application_number' => $app->application_number,
            'reference_number' => $app->reference_number,
            'message' => "An immigration officer has queried document(s) on application {$app->application_number}. Action is required.",
            'queries' => $items,
            'url' => "/portal/applications/{$app->id}",
            'action_label' => 'Upload Replacement Document',
        ];
    }
}
