<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\RecordContactConsentRequest;
use App\Http\Resources\Crm\ConsentDataMapRowResource;
use App\Http\Resources\Crm\ConsentRegisterRowResource;
use App\Http\Resources\Crm\ContactConsentsResource;
use App\Models\Contact;
use App\Models\User;
use App\Support\Crm\ConsentDataMap;
use App\Support\Crm\ConsentRegister;
use App\Support\Crm\ConsentVersionForPurpose;
use App\Support\Crm\ContactConsentView;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class ContactConsentController extends Controller
{
    #[DocumentedResponse(status: 200, type: 'array{data: list<App\\Http\\Resources\\Crm\\ConsentRegisterRowResource>}')]
    public function register(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        return ConsentRegisterRowResource::collection(ConsentRegister::rows());
    }

    #[DocumentedResponse(status: 200, type: 'array{data: list<App\\Http\\Resources\\Crm\\ConsentDataMapRowResource>}')]
    public function dataMap(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        return ConsentDataMapRowResource::collection(ConsentDataMap::rows());
    }

    public function show(Contact $contact): ContactConsentsResource
    {
        $this->authorize('view', $contact);

        return new ContactConsentsResource(ContactConsentView::forContact($contact));
    }

    public function store(RecordContactConsentRequest $request, Contact $contact, RecordContactConsent $action): ContactConsentsResource
    {
        $this->authorize('recordConsent', $contact);

        $actor = $request->user();
        $purpose = $request->purpose();
        $version = $request->validated('version');
        $version = is_string($version) && trim($version) !== '' ? trim($version) : ConsentVersionForPurpose::current($purpose);

        if ($version === null) {
            throw ValidationException::withMessages([
                'version' => ['A text version is required for this purpose. None is published.'],
            ]);
        }

        $action->handle(
            $contact,
            $purpose,
            granted: $request->granted(),
            version: $version,
            capturePoint: ConsentCapturePoint::Staff,
            ip: null,
            recordedBy: $actor instanceof User ? $actor : null,
            howObtained: (string) $request->validated('how_obtained'),
        );

        return new ContactConsentsResource(ContactConsentView::forContact($contact));
    }
}
