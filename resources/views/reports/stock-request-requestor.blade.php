<!DOCTYPE html>
<html>
    <head>
        <title>Stock Request Report</title>
        <style>
            body {
                font-family: sans-serif;
                font-size: 12px;
            }
            h2 {
                text-align: center;
                margin-top: 0;
            }
            .info, .items {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .info td {
                padding: 4px;
            }
            .items th, .items td {
                border: 1px solid #000;
                padding: 6px;
                text-align: left;
            }
        </style>
    </head>
<body>
    <table width="100%" style="border-bottom: 2px solid #000; margin-bottom: 20px;">
        <tr>
            <td width="80px" style="vertical-align: top;">
                <img src="{{ public_path('images/pmc-header.png') }}" alt="PMC Logo" style="height: 60px;">
            </td>
            <td style="text-align: right; font-size: 11px; vertical-align: top;">
                <strong>Warehouse @ Mill Site | Mine Site</strong><br>
                Phone: Local 2107 | 2134<br>
                Fax: (082) 233-2475<br>
                E-mail: mcd@philsagamining.com
            </td>
        </tr>
    </table>

    <h2>Stock Transfer Request</h2>

    <table class="info">
        <tr>
            <td><strong>Transaction No:</strong></td>
            <td>{{ $stockRequest->transaction_no }}</td>
        </tr>
        <tr>
            <td><strong>Date Needed:</strong></td>
            <td>{{ $stockRequest->date_needed ? \Carbon\Carbon::parse($stockRequest->date_needed)->toFormattedDateString() : 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Date/Time Filed:</strong></td>
            <td>
                {{ \Carbon\Carbon::parse($stockRequest->date_filed)->format('F j, Y') }}
                {{ $stockRequest->time_filed ? \Carbon\Carbon::parse($stockRequest->time_filed)->format('h:i A') : '' }}
            </td>
        </tr>
        <tr>
            <td><strong>From:</strong></td>
            <td>{{ $stockRequest->origin ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>To:</strong></td>
            <td>{{ $stockRequest->dept ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Remarks (delivery instruction):</strong></td>
            <td>{{ $stockRequest->remarks ?? 'N/A' }}</td>
        </tr>
    </table>

    <h3>Requested Items</h3>
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Stock Code</th>
                <th>Item Description</th>
                <th>Unit</th>
                <th>OEM</th>
                <th>Requested QTY</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requested_items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->stock_code }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->uom }}</td>
                <td>{{ $products[$item->stock_code]->oem ?? 'N/A' }}</td>
                <td>{{ $item->requested_qty }}</td>
                <td>{{ $item->remarks }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="info">
        <tr>
            <td><strong>Requested by:</strong></td>
            <td>{{ $stockRequest->requestor ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><br><strong>Approved by:</strong></td>
            <td><br>
                {{ $stockRequest->approved_by ?? 'N/A' }}
                {{ $stockRequest->approved_at ? ' @'.\Carbon\Carbon::parse($stockRequest->approved_at)->format('F j, Y h:i A') : '' }}</td>
        </tr>
        <tr>
            <td><br><strong>Received by:</strong></td>
            <td><br>
                {{ $stockRequest->received_by ?? 'N/A' }}
                {{ $stockRequest->received_at ? ' @'.\Carbon\Carbon::parse($stockRequest->received_at)->format('F j, Y h:i A') : '' }}
            </td>
        </tr>
    </table>

    <p><em>Generated on {{ \Carbon\Carbon::now()->toDayDateTimeString() }}</em></p>
</body>
</html>
