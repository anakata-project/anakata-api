<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Contacts\UndoContactMerge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UndoContactMergeRequest;
use App\Http\Resources\Crm\ContactMergeResource;
use App\Http\Resources\Crm\ContactUnmergeResultResource;
use App\Models\ContactMerge;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ContactMergeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContactMerge::class);

        $merges = ContactMerge::query()
            ->orderByDesc('merged_at')
            ->orderByDesc('id')
            ->paginate(50);

        return ContactMergeResource::collection($merges);
    }

    public function undo(UndoContactMergeRequest $request, ContactMerge $merge, UndoContactMerge $action): ContactUnmergeResultResource
    {
        $this->authorize('undo', $merge);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $result = $action->handle($merge, (string) $request->validated('reason'), $actor);

        return new ContactUnmergeResultResource($result);
    }
}
