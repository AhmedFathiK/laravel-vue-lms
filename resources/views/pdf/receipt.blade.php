<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #444;
            margin: 0;
        }
        .meta {
            margin-bottom: 30px;
        }
        .meta table {
            width: 100%;
        }
        .meta td {
            vertical-align: top;
        }
        .meta .right {
            text-align: right;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details th, .details td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .details th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 50px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .amount {
            font-weight: bold;
        }
        .status {
            text-transform: uppercase;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Receipt</h1>
            <p>Thank you for your purchase</p>
        </div>

        <div class="meta">
            <table>
                <tr>
                    <td>
                        <strong>Billed To:</strong><br>
                        {{ $user->name }}<br>
                        {{ $user->email }}
                    </td>
                    <td class="right">
                        <strong>Receipt #:</strong> {{ $receipt->receipt_number }}<br>
                        <strong>Date:</strong> {{ $receipt->created_at->format('F j, Y') }}<br>
                        <strong>Payment Method:</strong> {{ ucfirst($payment->payment_method ?? 'Manual') }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="details">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $receipt->item_name }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $receipt->item_type)) }}</td>
                    <td>{{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="right"><strong>Total Paid</strong></td>
                    <td class="amount">{{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>If you have any questions about this receipt, please contact support.</p>
            <p>&copy; {{ date('Y') }} LMS Platform. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
