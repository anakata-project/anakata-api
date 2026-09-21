@extends('mail.documents.layout')

@section('body')
    <p>Please find attached the wire transfer instructions for reservation <b>{{ $booking->displayReference() }}</b>.</p>
    <p>
        Amount due: <b>{{ \App\Support\Money::format((int) ($document->snapshot['amount'] ?? 0)) }}</b><br>
        Payment reference: <b>{{ $booking->displayReference() }} — include in all transfers</b>
    </p>
@endsection
