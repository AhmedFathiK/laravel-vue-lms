<!DOCTYPE html>
<html>
<head>
    <title>Subscription Receipt</title>
</head>
<body>
    <h1>Thank you for your subscription!</h1>
    <p>Hello {{ $receipt->user->name }},</p>
    <p>We have received your payment for <strong>{{ $receipt->item_name }}</strong>.</p>
    <p>Please find your receipt attached to this email.</p>
    <p>Thank you,</p>
    <p>The Team</p>
</body>
</html>