@extends('mail.documents.layout')

@section('body')
    <p>Please find attached your booking confirmation and invoice for reservation <b>{{ $booking->displayReference() }}</b>.</p>
    <p>The PDF is the issued document. Reply to this email if you have any questions.</p>
@endsection
