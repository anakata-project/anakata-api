@extends('mail.documents.layout')

@section('body')
    <p>Please find attached the final invoice for reservation <b>{{ $booking->displayReference() }}</b>.</p>
    <p>The PDF is the issued document. Reply to this email if you have any questions.</p>
@endsection
