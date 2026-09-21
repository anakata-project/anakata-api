@extends('mail.documents.layout')

@section('body')
    <p>Please use this link to pay the <b>{{ $purpose }}</b> of <b>{{ $amount }}</b> for reservation <b>{{ $booking->displayReference() }}</b>.</p>
    @if ($dueDate)
        <p>Due date: <b>{{ $dueDate }}</b>.</p>
    @else
        <p>The deposit is due at booking confirmation. The page captures the required declarations and billing data.</p>
    @endif
    <p style="margin: 18px 0;">
        <a href="{{ $completeUrl }}" style="display: inline-block; padding: 10px 18px; background: #2c4a3a; color: #f4efe4; text-decoration: none; letter-spacing: 0.08em; font-size: 13px;">Pay securely</a>
    </p>
    <p>A wire option is available: ask the team for the wire-instructions PDF and the {{ $booking->displayReference() }} payment reference.</p>
@endsection
