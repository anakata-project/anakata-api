<x-mail::message>
# Charter enquiry

**{{ $enquiry->contact->name }}** asked about a private charter.

@if ($enquiry->departure)
**Departure:** {{ $enquiry->departure->date->toDateString() }}
@elseif ($enquiry->preferred_from && $enquiry->preferred_to)
**Preferred dates:** {{ $enquiry->preferred_from->toDateString() }} – {{ $enquiry->preferred_to->toDateString() }}
@endif

**Guests:** {{ $enquiry->guests }}

{{ $enquiry->message }}

@if ($enquiry->contact->email)
**Email:** {{ $enquiry->contact->email }}
@endif
@if ($enquiry->contact->phone)
**Phone:** {{ $enquiry->contact->phone }}
@endif
</x-mail::message>
