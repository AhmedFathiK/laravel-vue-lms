<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { margin-bottom: 20px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details th, .details td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
        .total { text-align: right; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payment Receipt</h1>
        <p>Receipt #: {{ $receipt->receipt_number }}</p>
        <p>Date: {{ $receipt->created_at->format('Y-m-d') }}</p>
    </div>

    <div class="details">
        <h3>Customer Details</h3>
        <p>Name: {{ $receipt->user->name }}</p>
        <p>Email: {{ $receipt->user->email }}</p>
    </div>

    <div class="details">
        <h3>Order Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $receipt->item_type === 'subscription_plan' ? 'Subscription' : 'Course' }}</td>
                    <td>{{ $receipt->item_name }}</td>
                    <td>{{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="total">
        <p>Total Paid: {{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}</p>
    </div>

    <div class="footer" style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
        <p>Thank you for your purchase!</p>
    </div>
</body>
</html>