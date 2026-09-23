<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\UpdateAutomation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateAutomationRequest;
use App\Http\Resources\Crm\AutomationResource;
use App\Models\AutomationSetting;
use App\Models\User;
use App\Support\Automations\AutomationCatalogue;
use App\Support\Automations\AutomationRow;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AutomationController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\AutomationResource>}',
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AutomationSetting::class);

        return AutomationResource::collection(AutomationRow::all());
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\AutomationResource')]
    public function update(UpdateAutomationRequest $request, string $key, UpdateAutomation $update): AutomationResource
    {
        $definition = AutomationCatalogue::find($key);

        if ($definition === null) {
            abort(404);
        }

        $setting = AutomationSetting::query()->firstOrNew(['key' => $key]);
        $this->authorize('update', $setting);

        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new HttpException(403, 'You cannot change an automation.');
        }

        $update->handle(
            $definition,
            $request->boolean('enabled'),
            $request->string('reason')->toString(),
            $actor,
        );

        $row = AutomationRow::find($key);

        if (! $row instanceof AutomationRow) {
            throw new HttpException(404);
        }

        return new AutomationResource($row);
    }
}
