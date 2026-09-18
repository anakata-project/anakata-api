<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use App\Models\Concerns\HasAuditColumns;
use App\Support\History\History;

arch('crm controllers do not use money or booking write paths')
    ->expect('App\Http\Controllers\Crm')
    ->not->toUse([
        'App\Actions\Bookings',
        'App\Actions\Payments',
        'App\Actions\Refunds',
        'App\Actions\Commissions',
        'App\Actions\Documents',
        'App\Services\Pricing',
    ]);

arch('models use HasAuditColumns')
    ->expect('App\Models')
    ->toUseTrait(HasAuditColumns::class)
    ->ignoring([
        ChangeHistory::class,
        HasAuditColumns::class,
    ]);

arch('controllers do not write history')
    ->expect('App\Http\Controllers')
    ->not->toUse(History::class);

arch('every class in app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug helpers in app')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray']);

arch('no debug helpers in database')
    ->expect('Database')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray']);

arch('env is not used in app')
    ->expect('App')
    ->not->toUse('env');
