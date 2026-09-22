<?php

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Actions\Privacy\CompleteSubjectRequest;
use App\Actions\Privacy\EraseContact;
use App\Actions\Privacy\ExportSubjectAccess;
use App\Actions\Privacy\OpenSubjectRequest;
use App\Actions\Privacy\RejectSubjectRequest;
use App\Enums\SubjectRequestChannel;
use App\Enums\SubjectRequestStatus;
use App\Enums\SubjectRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\CloseSubjectRequestRequest;
use App\Http\Requests\Privacy\EraseSubjectRequestRequest;
use App\Http\Requests\Privacy\StoreSubjectRequestRequest;
use App\Http\Resources\Privacy\SubjectRequestResource;
use App\Models\Contact;
use App\Models\SubjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SubjectRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SubjectRequest::query()->orderBy('due_at')->orderBy('id');

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if ($request->query('overdue') === '1') {
            $query->where('status', SubjectRequestStatus::Open->value)->where('due_at', '<', now());
        }

        return response()->json([
            'data' => SubjectRequestResource::collection($query->get())->resolve(),
        ]);
    }

    public function show(SubjectRequest $subjectRequest): SubjectRequestResource
    {
        return new SubjectRequestResource($subjectRequest);
    }

    public function store(StoreSubjectRequestRequest $request, OpenSubjectRequest $open): SubjectRequestResource
    {
        $contact = Contact::query()->findOrFail($request->integer('contact_id'));
        $actor = $request->user();

        if ($actor === null) {
            throw new HttpException(403, 'You cannot record a subject request.');
        }

        $notes = $request->input('notes');

        $created = $open->handle(
            $contact,
            SubjectRequestType::from($request->string('type')->toString()),
            Carbon::parse($request->string('received_at')->toString()),
            SubjectRequestChannel::from($request->string('channel')->toString()),
            $actor,
            is_string($notes) ? $notes : null,
        );

        return new SubjectRequestResource($created);
    }

    public function export(SubjectRequest $subjectRequest, ExportSubjectAccess $export): SubjectRequestResource
    {
        return new SubjectRequestResource($export->handle($subjectRequest));
    }

    public function download(SubjectRequest $subjectRequest): StreamedResponse
    {
        if ($subjectRequest->export_path === null || ! Storage::disk('local')->exists($subjectRequest->export_path)) {
            throw new HttpException(404, 'This request has no export.');
        }

        return Storage::disk('local')->download($subjectRequest->export_path, 'access-'.$subjectRequest->id.'.zip');
    }

    public function complete(CloseSubjectRequestRequest $request, SubjectRequest $subjectRequest, CompleteSubjectRequest $complete): SubjectRequestResource
    {
        $actor = $request->user();

        if ($actor === null) {
            throw new HttpException(403, 'You cannot complete a subject request.');
        }

        return new SubjectRequestResource($complete->handle(
            $subjectRequest,
            $actor,
            $request->string('verified_how')->toString(),
            $request->string('outcome')->toString(),
        ));
    }

    public function erase(EraseSubjectRequestRequest $request, SubjectRequest $subjectRequest, EraseContact $erase): SubjectRequestResource
    {
        $actor = $request->user();

        if ($actor === null) {
            throw new HttpException(403, 'You cannot erase a contact.');
        }

        return new SubjectRequestResource($erase->handle(
            $subjectRequest,
            $actor,
            $request->string('verified_how')->toString(),
            $request->string('confirmation')->toString(),
        ));
    }

    public function reject(CloseSubjectRequestRequest $request, SubjectRequest $subjectRequest, RejectSubjectRequest $reject): SubjectRequestResource
    {
        $actor = $request->user();

        if ($actor === null) {
            throw new HttpException(403, 'You cannot reject a subject request.');
        }

        return new SubjectRequestResource($reject->handle(
            $subjectRequest,
            $actor,
            $request->string('verified_how')->toString(),
            $request->string('outcome')->toString(),
        ));
    }
}
