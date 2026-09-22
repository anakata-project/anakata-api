<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertNotificationStatus;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\User;
use App\Support\Alerts\AlertRegistry;
use App\Support\Alerts\AlertSubject;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     kind: AlertKind,
     *     kind_label: string,
     *     severity: AlertSeverity,
     *     title: string,
     *     sentence: string,
     *     state: 'open'|'acknowledged'|'resolved',
     *     raised_at: string|null,
     *     acknowledged_at: string|null,
     *     resolved_at: string|null,
     *     resolution: string|null,
     *     subject: array{type: string, id: int|null, reference: string, href: string},
     *     task: array{id: int, title: string, href: string}|null,
     *     may_acknowledge: bool,
     *     notifications: list<array{
     *         user_id: int,
     *         user_name: string,
     *         status: AlertNotificationStatus,
     *         error: string|null,
     *         sent_at: string|null,
     *         attempts: int
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        $definition = AlertRegistry::get($this->kind);
        $actor = $request->user();
        $subject = AlertSubject::describe($this->resource);
        $task = $this->crmTask;
        $notifications = [];

        foreach ($this->notifications as $row) {
            $notifications[] = [
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user->name,
                'status' => $this->notificationStatus($row),
                'error' => $this->notificationError($row),
                'sent_at' => Iso::utc($row->sent_at),
                'attempts' => (int) $row->attempts,
            ];
        }

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => $definition->label(),
            'severity' => $this->severity,
            'title' => $this->title,
            'sentence' => $this->sentence,
            'state' => $this->state(),
            'raised_at' => Iso::utc($this->raised_at),
            'acknowledged_at' => Iso::utc($this->acknowledged_at),
            'resolved_at' => Iso::utc($this->resolved_at),
            'resolution' => $this->resolution,
            'subject' => $subject,
            'task' => $task === null ? null : [
                'id' => $task->id,
                'title' => $task->title,
                'href' => '/crm/sales/tasks',
            ],
            'may_acknowledge' => $this->mayAcknowledge($actor),
            'notifications' => $notifications,
        ];
    }

    private function mayAcknowledge(mixed $actor): bool
    {
        return $this->state() === 'open'
            && $actor instanceof User
            && AlertRegistry::sees($actor, $this->kind);
    }

    private function notificationStatus(AlertNotification $row): AlertNotificationStatus
    {
        return $row->status;
    }

    private function notificationError(AlertNotification $row): ?string
    {
        return $row->error;
    }
}
