@extends('mail.documents.layout')

@section('body')
    <p>Your expedition on {{ $booking->departure->date->toDateString() }} ({{ $booking->reference }}) has ended. The survey takes a few minutes.</p>
    <p style="margin: 18px 0;">
        <a href="{{ $surveyUrl }}" style="display: inline-block; padding: 10px 18px; background: #2c4a3a; color: #f4efe4; text-decoration: none; letter-spacing: 0.08em; font-size: 13px;">Open the survey</a>
    </p>
    <p>Questions can go to {{ $replyTo }}.</p>
@endsection
