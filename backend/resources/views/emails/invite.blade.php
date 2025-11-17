<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>You're invited</title>
</head>
<body>
<p>Hello,</p>
<p>You have been invited to join the Arabia Talents CRM. Click the link below to finish setting your password and log in:</p>
<p><a href="{{ $link }}">{{ $link }}</a></p>
<p>This link expires at {{ $expiresAt?->toDayDateTimeString() }}.</p>
<p>Thanks,<br/>Arabia Talents CRM Team</p>
</body>
</html>
