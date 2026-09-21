@extends('mail.documents.layout')

@section('body')
    <p>We confirm receipt of your payment for reservation <b>{{ $booking->displayReference() }}</b>.</p>
    <p>The payment confirmation is attached.</p>
@endsection
