<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', nexus_setting('store.name'))</title>
</head>
<body style="margin:0;padding:0;background:#f3f6f4;font-family:Arial,Helvetica,sans-serif;color:#16221f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f4;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e1e9e5;">
                    <tr>
                        <td style="padding:26px 32px;border-bottom:1px solid #e8efeb;">
                            <span style="display:inline-block;width:34px;height:34px;line-height:34px;text-align:center;background:#0f766e;color:#ffffff;border-radius:10px;font-weight:bold;font-size:18px;vertical-align:middle;">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                            <span style="font-size:17px;font-weight:bold;margin-left:10px;vertical-align:middle;">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
                        </td>
                    </tr>
                    <tr><td style="padding:32px;">@yield('content')</td></tr>
                    <tr>
                        <td style="padding:20px 32px;background:#f8fbf9;border-top:1px solid #e8efeb;color:#6a7974;font-size:12px;line-height:1.6;">
                            {{ nexus_setting('store.footer_text', 'Small-batch goods chosen with care.') }}<br>
                            {{ nexus_setting('store.email') }} · {{ url('/') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
