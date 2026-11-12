@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 10px;font-size:26px;">We could not process your payment</h1>
    <p style="margin:0 0 18px;color:#5f6f69;line-height:1.65;">Order <strong>{{ $order->number }}</strong> is still reserved, but the payment did not go through. You can reply to this email and we will help you complete it.</p>
    <p style="margin:0;"><a href="{{ $order->confirmationUrl() }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">Review order</a></p>
@endsection
