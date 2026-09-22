<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = AlertRegistry::get($this->kind);
        $actor = $request->user();
        $subject = AlertSubject::describe($this->resource);
        $task = $this->crmTask;

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $definition->label(),
            'severity' => $this->severity->value,
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
            'may_acknowledge' => $this->state() === 'open'
                && $actor instanceof User
                && AlertRegistry::sees($actor, $this->kind),
            'notifications' => $this->notifications->map(fn (AlertNotification $row): array => [
                'user_id' => $row->user_id,
                'user_name' => $row->user->name,
                'status' => $row->status->value,
                'error' => $row->error,
                'sent_at' => Iso::utc($row->sent_at),
                'attempts' => $row->attempts,
            ])->all(),
        ];
    }
}
