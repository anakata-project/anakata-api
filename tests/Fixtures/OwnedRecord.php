<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class OwnedRecord extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];
}
