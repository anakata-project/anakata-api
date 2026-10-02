<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$actionUrl">
ANAKATA
</x-mail::header>
</x-slot:header>

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# Hello
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Facts --}}
<x-mail::panel>
**Window** {{ $window }}

**Rows** {{ $rows }}
</x-mail::panel>

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Regards,<br>
Anakata
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
<x-mail::subcopy>
If the button does not open, copy this link into your browser: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Anakata · Intimate yacht expeditions · Galápagos
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
