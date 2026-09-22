@extends('mail.documents.layout')

@section('body')
    <p>We still need passenger details for your expedition departing {{ $booking->departure->date->toDateString() }} ({{ $booking->reference }}).</p>
    <p>Please add the missing names, dates of birth, nationalities, passports and insurance before we file the passenger list.</p>
    <p style="margin: 18px 0;">
        <a href="{{ $completeUrl }}" style="display: inline-block; padding: 10px 18px; background: #2c4a3a; color: #f4efe4; text-decoration: none; letter-spacing: 0.08em; font-size: 13px;">Complete passenger details</a>
    </p>
    <p>If you have already sent them, thank you — please disregard this message. Questions can go to {{ $replyTo }}.</p>
@endsection
