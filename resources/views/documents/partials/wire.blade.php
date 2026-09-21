@php $bank = $snapshot['bank'] ?? []; @endphp
<div class="dsec">Wire transfer instructions</div>
<table class="dg2">
    <tr>
        <td>
            <table class="dkv">
                <tr><td>Bank</td><td>{{ $bank['bank_name'] ?? '' }}</td></tr>
                <tr><td>Account name</td><td>{{ $bank['account_name'] ?? '' }}</td></tr>
                <tr><td>Account number</td><td>{{ $bank['account_number'] ?? '' }}</td></tr>
            </table>
        </td>
        <td>
            <table class="dkv">
                <tr><td>ABA / Routing</td><td>{{ $bank['routing'] ?? '' }}</td></tr>
                <tr><td>SWIFT / BIC</td><td>{{ $bank['swift'] ?? '' }}</td></tr>
                <tr><td>Payment reference</td><td>{{ $bank['payment_reference'] ?? '' }}</td></tr>
            </table>
        </td>
    </tr>
</table>
