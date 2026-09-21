@php
    $payments = $snapshot['payments'] ?? [];
    $totals = $snapshot['totals'] ?? [];
@endphp
<div class="dsec">Payment history</div>
@if($payments === [])
    <div class="dnote">No payments recorded yet.</div>
@else
    <table class="dt r3">
        <thead>
            <tr><th>Date</th><th>Method</th><th>Amount (USD)</th><th>Reference</th></tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment['date'] }}</td>
                    <td>{{ $payment['method'] }}</td>
                    <td>{{ \App\Support\Money::formatDocument((int) $payment['amount']) }}</td>
                    <td>{{ $payment['reference'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
<table class="dg2" style="margin-top:14px">
    <tr>
        <td>
            <table class="dkv"><tr><td>Total paid to date</td><td>{{ \App\Support\Money::formatDocument((int) ($totals['paid'] ?? 0)) }}</td></tr></table>
        </td>
        <td>
            <table class="dkv"><tr><td><b>Current balance due</b></td><td><b>USD {{ \App\Support\Money::formatDocument((int) ($totals['balance'] ?? 0)) }}</b></td></tr></table>
        </td>
    </tr>
</table>
