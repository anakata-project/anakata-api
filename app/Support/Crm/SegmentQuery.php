<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\SegmentKind;
use App\Models\Contact;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;

final class SegmentQuery
{
    public const CAP = 50;

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function apply(Builder $query, Segment $segment): Builder
    {
        $query->notMerged();

        $compiled = SegmentCompiler::compile($segment->conditions);
        $query->whereRaw($compiled['sql'], $compiled['bindings']);

        if ($segment->kind === SegmentKind::Marketing) {
            $query->whereRaw('NOT ('.Suppression::sql().')');
        }

        return $query;
    }

    public function count(Segment $segment): int
    {
        return $this->apply(Contact::query(), $segment)->count();
    }
}
