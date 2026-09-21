<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexContactTimelineRequest;
use App\Http\Resources\Crm\ContactTimelineItemResource;
use App\Models\Contact;
use App\Support\Crm\ContactTimeline;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ContactTimelineController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\ContactTimelineItemResource>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string|null, per_page: int, to: int|null, total: int}}',
    )]
    public function __invoke(IndexContactTimelineRequest $request, Contact $contact): AnonymousResourceCollection
    {
        $this->authorize('view', $contact);

        return ContactTimelineItemResource::collection(
            ContactTimeline::page($contact, $request->integer('per_page', 50)),
        );
    }
}
