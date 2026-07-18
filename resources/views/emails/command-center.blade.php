<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#f8fafc;"><tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            <tr><td style="padding:24px 32px;background:#020617;color:#ffffff;"><strong style="font-size:18px;letter-spacing:1px;">RETAILPOS</strong><span style="margin-left:8px;color:#94a3b8;">Command Center</span></td></tr>
            <tr><td style="padding:32px;"><h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;">{{ $heading }}</h1><p style="margin:0 0 16px;line-height:1.6;">{{ $greeting }}</p><p style="margin:0 0 20px;line-height:1.6;white-space:pre-line;">{{ $messageText }}</p>
                @if($details)<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;border-collapse:collapse;background:#f8fafc;">@foreach($details as $label => $value)<tr><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:38%;">{{ $label }}</td><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;">{{ $value }}</td></tr>@endforeach</table>@endif
                @if($actionUrl)<p style="margin:24px 0 0;"><a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 18px;background:#0f766e;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">{{ $actionLabel ?: 'Open Command Center' }}</a></p>@endif
            </td></tr>
            <tr><td style="padding:20px 32px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.6;">RetailPOS support: India +91 8072682244 &middot; Malaysia +60 104305163 &middot; Singapore +65 92475024<br>info@retailpos.biz &middot; global@retailpos.biz</td></tr>
        </table>
    </td></tr></table>
</body>
</html>
