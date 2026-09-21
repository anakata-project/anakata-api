@php $billing = $snapshot['billing'] ?? []; @endphp
<div class="dsec">Guest &amp; billing</div>
<table class="dkv">
    <tr><td>Guest</td><td>{{ $billing['guest'] ?? '' }}</td></tr>
    <tr><td>Address</td><td>{!! nl2br(e($billing['address'] ?? '')) !!}</td></tr>
    <tr>
        <td>Email</td>
        <td>{{ $billing['email'] ?? '' }}@if(! empty($billing['phone'])) · {{ $billing['phone'] }}@endif</td>
    </tr>
    @if(! empty($billing['agent']))
        <tr><td>Agent</td><td>{{ $billing['agent']['name'] }} · {{ $billing['agent']['contact'] }}</td></tr>
    @endif
    @if(! empty($billing['group']))
        <tr><td>Group</td><td>{{ $billing['group']['reference'] }} · coordinator {{ $billing['group']['coordinator'] }}</td></tr>
    @endif
</table>
