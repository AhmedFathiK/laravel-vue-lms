<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt #{{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            background-color: #fff;
            margin: 0;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .header .logo {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .header .receipt-details {
            text-align: right;
        }
        .details {
            margin-bottom: 20px;
        }
        .details table {
            width: 100%;
        }
        .details .billed-to {
            vertical-align: top;
        }
        .details .payment-details {
            vertical-align: top;
            text-align: right;
        }
        h1, h2, h3, h4, h5, h6 {
            margin: 0;
        }
        h6 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #eee;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .items-table .text-right {
            text-align: right;
        }
        .total-section {
            float: right;
            width: 250px;
        }
        .total-section table {
            width: 100%;
        }
        .total-section td {
            padding: 5px 0;
        }
        .total-section .total {
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header clearfix">
            <div style="float: left; width: 50%;">
                <h1 class="logo">Your Company</h1>
            </div>
            <div class="receipt-details" style="float: right; width: 50%;">
                <h2>Receipt</h2>
                <p><strong>Receipt #:</strong> {{ $receipt->receipt_number }}</p>
                <p><strong>Date:</strong> {{ $receipt->created_at->format('F j, Y') }}</p>
            </div>
        </div>

        <div class="details clearfix">
            <div class="billed-to" style="float: left; width: 50%;">
                <h6>Billed To</h6>
                <p>
                    {{ $receipt->user->full_name }}<br>
                    {{ $receipt->user->email }}
                    @if($receipt->user->phone_number)
                        <br>{{ $receipt->user->phone_number }}
                    @endif
                </p>
            </div>
            <div class="payment-details" style="float: right; width: 50%;">
                <h6>Payment Details</h6>
                <p>
                    <strong>Method:</strong> {{ ucwords(str_replace('_', ' ', $receipt->payment->payment_method)) }}<br>
                    <strong>Transaction ID:</strong> {{ $receipt->payment->transaction_id ?? 'N/A' }}
                </p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $receipt->item_name }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $receipt->item_type)) }}</td>
                    <td class="text-right">{{ number_format($receipt->amount, 2) }} {{ $receipt->currency }}</td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">{{ number_format($receipt->amount, 2) }} {{ $receipt->currency }}</td>
                </tr>
                <tr>
                    <td>Tax (0%):</td>
                    <td class="text-right">0.00 {{ $receipt->currency }}</td>
                </tr>
                <tr>
                    <td colspan="2"><hr></td>
                </tr>
                <tr class="total">
                    <td>Total:</td>
                    <td class="text-right">{{ number_format($receipt->amount, 2) }} {{ $receipt->currency }}</td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>If you have any questions, please contact us at support@example.com.</p>
        </div>
    </div>
</body>
</html>
