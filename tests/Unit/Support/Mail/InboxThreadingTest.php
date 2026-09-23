<?php

declare(strict_types=1);

use App\Support\Mail\InboundMessageId;
use App\Support\Mail\QuotedReply;
use App\Support\Mail\SubjectThread;
use Carbon\CarbonImmutable;

test('subjects lose reply and forward prefixes', function (): void {
    expect(SubjectThread::normalise('Re: Re: Fwd: Cabins'))->toBe('Cabins');
    expect(SubjectThread::normalise('FW: Hello'))->toBe('Hello');
    expect(SubjectThread::normalise('   '))->toBe('(no subject)');
});

test('quoted replies and signatures are stripped from the preview text', function (): void {
    expect(QuotedReply::strip("Hi there\n\nOn Mon, 1 Jan 2026 Guest wrote:\n> old line\n"))->toBe('Hi there');
    expect(QuotedReply::strip("Hi there\n-- \nJane Doe"))->toBe('Hi there');
    expect(QuotedReply::strip("Hi there\n-----Original Message-----\nFrom: old@example.com"))->toBe('Hi there');
});

test('a missing message id becomes a stable hash and headers are normalised', function (): void {
    $sentAt = CarbonImmutable::parse('2026-09-23T12:00:00Z');
    $first = InboundMessageId::canonical(null, 'A@B.C', $sentAt, 'Cabins', '<p>Hi</p>');
    $again = InboundMessageId::canonical('', 'a@b.c', $sentAt, 'Cabins', '<p>Hi</p>');

    expect($first)->toStartWith('missing:')
        ->and($again)->toBe($first)
        ->and(InboundMessageId::normalise('abc@guest.test'))->toBe('<abc@guest.test>')
        ->and(InboundMessageId::ids('<a@x> <b@x>'))->toBe(['<a@x>', '<b@x>']);
});
