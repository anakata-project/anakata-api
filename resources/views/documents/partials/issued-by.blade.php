@php $issuer = $snapshot['issuer'] ?? []; @endphp
<div class="dsec">Issued by</div>
<table class="dkv">
    <tr><td>Entity</td><td>{{ $issuer['name'] ?? '' }}</td></tr>
    <tr><td>Address</td><td>{!! nl2br(e(implode("\n", $issuer['address_lines'] ?? []))) !!}</td></tr>
    <tr><td>Email</td><td>{{ $issuer['email'] ?? '' }} · {{ $issuer['website'] ?? '' }}</td></tr>
    <tr><td>EIN</td><td>{{ $issuer['ein'] ?? '' }}</td></tr>
</table>
