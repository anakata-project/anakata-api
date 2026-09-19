<?php

declare(strict_types=1);

use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;

test('auth notifications are queued after commit', function (): void {
    $invitation = new UserInvitation('token', null, 'Admin');
    $reset = new ResetPasswordNotification('token');

    expect($invitation)->toBeInstanceOf(ShouldQueue::class);
    expect($reset)->toBeInstanceOf(ShouldQueue::class);
    expect($invitation->afterCommit)->toBeTrue();
    expect($reset->afterCommit)->toBeTrue();
});
