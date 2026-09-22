<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alerts;

use App\Actions\Alerts\AcknowledgeAlert;
use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Alerts\AlertKindResource;
use App\Http\Resources\Alerts\AlertListResource;
use App\Http\Resources\Alerts\AlertResource;
use App\Models\Alert;
use App\Models\User;
use App\Support\Alerts\AlertInbox;
use App\Support\Alerts\AlertRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AlertController extends Controller
{
    public function index(Request $request): AlertListResource
    {
        $this->authorize('viewAny', Alert::class);
        /** @var User $actor */
        $actor = $request->user();

        $filters = $request->validate([
            'state' => ['sometimes', 'nullable', Rule::in(['open', 'acknowledged', 'resolved'])],
            'severity' => ['sometimes', 'nullable', Rule::enum(AlertSeverity::class)],
            'kind' => ['sometimes', 'nullable', Rule::enum(AlertKind::class)],
            'section' => ['sometimes', 'nullable', Rule::in(['rms', 'crm'])],
        ]);

        return new AlertListResource(AlertInbox::build($actor, [
            'state' => $this->stringOrNull($filters['state'] ?? null),
            'severity' => $this->stringOrNull($filters['severity'] ?? null),
            'kind' => $this->stringOrNull($filters['kind'] ?? null),
            'section' => $this->stringOrNull($filters['section'] ?? null),
        ]));
    }

    public function kinds(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Alert::class);

        return AlertKindResource::collection(AlertRegistry::all());
    }

    public function acknowledge(Request $request, Alert $alert, AcknowledgeAlert $action): AlertResource
    {
        $this->authorize('acknowledge', $alert);
        /** @var User $actor */
        $actor = $request->user();

        if (! AlertRegistry::sees($actor, $alert->kind)) {
            throw new HttpException(403, 'You cannot acknowledge this alert.');
        }

        $alert = $action->handle($alert, $actor);
        $alert->load(['booking', 'payment.booking', 'delivery.booking', 'crmTask', 'notifications.user']);

        return new AlertResource($alert);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
