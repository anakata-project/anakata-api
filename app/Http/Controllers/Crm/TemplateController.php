<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\CreateTemplateDraft;
use App\Actions\Crm\PublishTemplateVersion;
use App\Actions\Crm\SendTemplateTest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\PreviewTemplateRequest;
use App\Http\Requests\Crm\PublishTemplateVersionRequest;
use App\Http\Requests\Crm\StoreTemplateDraftRequest;
use App\Http\Resources\Crm\CrmMessageTemplateResource;
use App\Http\Resources\Crm\CrmMessageTemplateVersionResource;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use App\Models\User;
use App\Support\Templates\TemplateRenderer;
use App\Support\Templates\TemplateVariableException;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TemplateController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\CrmMessageTemplateResource>}',
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MessageTemplate::class);

        $templates = MessageTemplate::query()->orderBy('key')->with('versions')->get();

        return CrmMessageTemplateResource::collection($templates);
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmMessageTemplateVersionResource')]
    public function show(MessageTemplate $template, int $version): CrmMessageTemplateVersionResource
    {
        $this->authorize('view', $template);

        return new CrmMessageTemplateVersionResource($this->version($template, $version));
    }

    #[DocumentedResponse(status: 201, type: 'App\\Http\\Resources\\Crm\\CrmMessageTemplateVersionResource')]
    public function storeDraft(StoreTemplateDraftRequest $request, MessageTemplate $template, CreateTemplateDraft $create): JsonResponse
    {
        $this->authorize('draft', $template);

        $version = $create->handle(
            $template,
            $request->string('subject')->toString(),
            $request->body(),
            $this->actor($request->user()),
        );

        return (new CrmMessageTemplateVersionResource($version))
            ->response()
            ->setStatusCode(201);
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmMessageTemplateVersionResource')]
    public function publish(
        PublishTemplateVersionRequest $request,
        MessageTemplate $template,
        int $version,
        PublishTemplateVersion $publish,
    ): CrmMessageTemplateVersionResource {
        $this->authorize('publish', $template);

        $saved = $publish->handle(
            $template,
            $this->version($template, $version),
            $request->string('approval_reference')->toString(),
            $this->actor($request->user()),
        );

        return new CrmMessageTemplateVersionResource($saved);
    }

    /**
     * @return array{subject: string, body: string}
     */
    #[DocumentedResponse(status: 200, type: 'array{subject: string, body: string}')]
    public function preview(PreviewTemplateRequest $request, MessageTemplate $template, TemplateRenderer $renderer): array
    {
        $this->authorize('preview', $template);

        [$contact, $booking] = $this->sample($request);
        $version = $this->selected($template, $request->integer('version') ?: null);

        try {
            $rendered = $renderer->render($version, $contact, $booking);
        } catch (TemplateVariableException $exception) {
            throw new HttpException(422, $exception->getMessage());
        }

        return [
            'subject' => $rendered->subject,
            'body' => $rendered->html,
        ];
    }

    /**
     * @return array{version: int, subject: string, status: string}
     */
    #[DocumentedResponse(status: 200, type: 'array{version: int, subject: string, status: string}')]
    public function testSend(PreviewTemplateRequest $request, MessageTemplate $template, SendTemplateTest $send): array
    {
        $this->authorize('testSend', $template);

        [$contact, $booking] = $this->sample($request);
        $version = $this->selected($template, $request->integer('version') ?: null);
        $row = $send->handle($template, $version, $contact, $booking, $this->actor($request->user()));

        return [
            'version' => $version->version,
            'subject' => (string) $row->getAttribute('rendered_subject'),
            'status' => $row->status->value,
        ];
    }

    private function version(MessageTemplate $template, int $number): MessageTemplateVersion
    {
        $version = $template->versions()->where('version', $number)->first();

        if (! $version instanceof MessageTemplateVersion) {
            throw new HttpException(404, 'That template version does not exist.');
        }

        return $version;
    }

    private function selected(MessageTemplate $template, ?int $number): MessageTemplateVersion
    {
        if ($number === null) {
            $version = $template->openDraft() ?? $template->publishedVersion();
        } else {
            $version = $template->versions()->where('version', $number)->first();
        }

        if (! $version instanceof MessageTemplateVersion) {
            throw new HttpException(404, 'That template version does not exist.');
        }

        return $version;
    }

    /**
     * @return array{0: Contact, 1: Booking|null}
     */
    private function sample(PreviewTemplateRequest $request): array
    {
        if ($request->filled('booking_id')) {
            $booking = Booking::query()->with('contact')->find($request->integer('booking_id'));

            if (! $booking instanceof Booking) {
                throw new HttpException(404, 'That booking does not exist.');
            }

            return [$booking->contact, $booking];
        }

        $contact = Contact::query()->find($request->integer('contact_id'));

        if (! $contact instanceof Contact) {
            throw new HttpException(404, 'That contact does not exist.');
        }

        return [$contact, null];
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            throw new HttpException(403, 'You cannot change a template.');
        }

        return $user;
    }
}
