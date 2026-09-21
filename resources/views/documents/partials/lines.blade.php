@php
    $rows = $rows ?? [];
    $headers = $headers ?? ['Concept', '# PAX', 'Per person (USD)', 'Amount (USD)'];
@endphp
<table class="dt r3">
    <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['concept'] }}</td>
                <td>{{ $row['qty'] }}</td>
                <td>{{ $row['rate'] === null ? '' : \App\Support\Money::formatDocument($row['rate']) }}</td>
                <td>{{ \App\Support\Money::formatDocument($row['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
