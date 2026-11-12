@extends('emails.layout')

@section('content')
    <p style="margin:0 0 8px;color:#0f766e;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">New order</p>
    <h1 style="margin:0 0 10px;font-size:26px;">{{ $order->number }} · {{ nexus_money($order->grand_total) }}</h1>
    <p style="margin:0 0 20px;color:#5f6f69;">{{ $order->customer_name }} ({{ $order->customer_email }}) placed an order through the {{ ucfirst($order->mode->value) }} store.</p>
    <p style="margin:0 0 20px;"><a href="{{ url('/admin/orders/'.$order->id) }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">Open order in admin</a></p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
        @foreach($order->items as $item)
            <tr><td style="padding:10px 0;border-bottom:1px solid #edf2ef;">{{ $item->quantity }} × {{ $item->product_name }}</td><td align="right">{{ nexus_money($item->line_total) }}</td></tr>
        @endforeach
    </table>
@endsection
