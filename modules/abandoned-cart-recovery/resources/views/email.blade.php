<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your checkout is waiting</title>
</head>
<body style="margin:0;background:#f3f5f2;font-family:Inter,Arial,sans-serif;color:#17231f">
    <div style="max-width:600px;margin:0 auto;padding:32px 20px">
        <div style="font-size:22px;font-weight:800;letter-spacing:-.02em;color:#0f766e">{{ $storeName }}</div>
        <div style="margin-top:24px;border-radius:18px;background:#ffffff;padding:32px;border:1px solid #e1e8e4">
            <div style="font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#8a9892">Saved for you</div>
            <h1 style="margin:8px 0 0;font-size:30px;line-height:1.15;letter-spacing:-.02em">You left something behind.</h1>
            <p style="margin:14px 0 0;font-size:14px;line-height:1.7;color:#5d6d66">
                Hi {{ $order->customer_name ?: 'there' }}, your checkout at {{ $storeName }} was not completed. We have kept the order together for you.
            </p>

            <table style="width:100%;margin-top:22px;border-collapse:collapse;font-size:13px">
                <tr><td style="padding:8px 0;border-bottom:1px solid #edf1ee;color:#8a9892">Order</td><td style="padding:8px 0;border-bottom:1px solid #edf1ee;text-align:right;font-weight:700">{{ $order->number }}</td></tr>
                @foreach($order->items as $item)
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #edf1ee">{{ $item->product_name }} × {{ $item->quantity }}</td>
                        <td style="padding:10px 0;border-bottom:1px solid #edf1ee;text-align:right">{{ nexus_money($item->line_total, $order->currency) }}</td>
                    </tr>
                @endforeach
                <tr><td style="padding:12px 0 0;font-weight:700">Total</td><td style="padding:12px 0 0;text-align:right;font-weight:800">{{ nexus_money($order->grand_total, $order->currency) }}</td></tr>
            </table>

            <a href="{{ $recoveryUrl }}" style="display:inline-block;margin-top:24px;border-radius:999px;background:#0f766e;padding:13px 24px;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none">Return to my order</a>
            <p style="margin:16px 0 0;font-size:11px;line-height:1.6;color:#8a9892">This link is unique to your order and expires in seven days. If you completed this order already, no action is needed.</p>
        </div>
        <p style="margin:18px 0 0;text-align:center;font-size:10px;color:#98a49d">You are receiving this because you started a checkout at {{ $storeName }}. This is a transactional message about your order.</p>
    </div>
</body>
</html>
