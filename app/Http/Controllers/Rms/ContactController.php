<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexContactsRequest;
use App\Http\Resources\Rms\ContactResource;
use App\Models\Booking;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ContactController extends Controller
{
    public function index(IndexContactsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('create', Booking::class);

        $search = trim((string) ($request->validated('q') ?? ''));

        if ($search === '') {
            return ContactResource::collection(new Collection);
        }

        $like = '%'.$search.'%';

        $contacts = Contact::query()
            ->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return ContactResource::collection($contacts);
    }
}
