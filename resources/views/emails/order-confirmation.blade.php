@extends('emails.layout')

@section('title', 'Order '.$order->number)

@section('content')
    <p style="margin:0 0 8px;color:#0f766e;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">Order confirmed</p>
    <h1 style="margin:0 0 10px;font-size:28px;line-height:1.2;">Thanks, {{ $order->customer_name }}.</h1>
    <p style="margin:0 0 24px;color:#5f6f69;line-height:1.65;">We have received order <strong>{{ $order->number }}</strong>. We will email you again when it ships.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:22px;">
        @foreach($order->items as $item)
            <tr>
                <td style="padding:12px 0;border-bottom:1px solid #edf2ef;">
                    <strong>{{ $item->product_name }}</strong>
                    @if($item->variant_name)<br><span style="color:#6a7974;font-size:12px;">{{ $item->variant_name }}</span>@endif
                    <br><span style="color:#6a7974;font-size:12px;">Qty {{ $item->quantity }} × {{ nexus_money($item->unit_price) }}</span>
                </td>
                <td align="right" style="padding:12px 0;border-bottom:1px solid #edf2ef;white-space:nowrap;">{{ nexus_money($item->line_total) }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
        <tr><td style="padding:4px 0;color:#6a7974;">Subtotal</td><td align="right">{{ nexus_money($order->subtotal) }}</td></tr>
        @if($order->discount_total > 0)<tr><td style="padding:4px 0;color:#0f766e;">Discount</td><td align="right">−{{ nexus_money($order->discount_total) }}</td></tr>@endif
        <tr><td style="padding:4px 0;color:#6a7974;">Shipping</td><td align="right">{{ $order->shipping_total > 0 ? nexus_money($order->shipping_total) : 'Free' }}</td></tr>
        @if($order->tax_total > 0)<tr><td style="padding:4px 0;color:#6a7974;">Tax</td><td align="right">{{ nexus_money($order->tax_total) }}</td></tr>@endif
        <tr><td style="padding:14px 0 0;font-size:17px;font-weight:bold;border-top:1px solid #e8efeb;">Total</td><td align="right" style="padding:14px 0 0;font-size:17px;font-weight:bold;border-top:1px solid #e8efeb;">{{ nexus_money($order->grand_total) }}</td></tr>
    </table>

    <div style="margin-top:26px;padding:16px;background:#f6faf8;border-radius:12px;font-size:13px;line-height:1.6;color:#5f6f69;">
        <strong style="color:#16221f;">Shipping to</strong><br>
        {{ $order->customer_name }}<br>
        {{ $order->shippingAddressLine() }}
    </div>

    <p style="margin:24px 0 0;font-size:13px;color:#6a7974;">You can view your order any time in your account.</p>
    <p style="margin:18px 0 0;"><a href="{{ $order->confirmationUrl() }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;font-size:14px;">View order</a></p>
@endsection
