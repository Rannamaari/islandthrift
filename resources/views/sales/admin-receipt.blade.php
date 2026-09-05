<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $format === 'a4' ? 'Tax Invoice' : 'Receipt' }} {{ $sale->sale_number }}</title>
    <style>
        @page { size: {{ $format === 'a4' ? 'A4' : '80mm auto' }}; margin: {{ $format === 'a4' ? '14mm' : '3mm' }}; }
        * { box-sizing: border-box; } body { margin: 0; color: #17212b; font: {{ $format === 'a4' ? '12px/1.45 Arial, sans-serif' : '12px/1.35 Arial, sans-serif' }}; } .receipt { margin: auto; position: relative; {{ $format === 'a4' ? 'max-width: 182mm;' : 'width: 74mm;' }} } .center { text-align: center; } .shop { font-size: {{ $format === 'a4' ? '22px' : '18px' }}; font-weight: 700; } .muted, small { color: #52616b; font-size: {{ $format === 'a4' ? '11px' : '10px' }}; } .rule { border-top: 1px dashed #66737d; margin: 9px 0; } table { border-collapse: collapse; width: 100%; } th { background: #edf2f5; text-align: left; } th, td { {{ $format === 'a4' ? 'border: 1px solid #b9c5cc; padding: 8px;' : 'padding: 3px 0;' }} vertical-align: top; } .num { text-align: right; white-space: nowrap; } .tax td { color: #52616b; font-size: 10px; } .totals div, .payments div { display: flex; justify-content: space-between; gap: 20px; padding: 3px 0; } .grand { border-top: 1px solid #17212b; font-size: 16px; font-weight: 700; margin-top: 5px; padding-top: 7px !important; } .watermark { color: #b42318; font-size: {{ $format === 'a4' ? '20px' : '13px' }}; font-weight: 800; letter-spacing: .12em; margin: 12px 0; text-align: center; } .invoice-head { border-bottom: 2px solid #17212b; display: flex; gap: 24px; justify-content: space-between; padding-bottom: 14px; } .invoice-title { text-align: right; } .invoice-title h1 { font-size: 24px; letter-spacing: .08em; margin: 0 0 8px; } .footer { border-top: 1px solid #b9c5cc; color: #52616b; margin-top: {{ $format === 'a4' ? '38px' : '14px' }}; padding-top: 12px; text-align: center; } .print-controls { position: fixed; right: 12px; top: 12px; } .print-controls button { background: #17212b; border: 0; border-radius: 8px; color: #fff; cursor: pointer; font-weight: 700; padding: 10px 14px; } @media print { body { width: auto; } .print-controls { display: none; } }
    </style>
</head>
<body>
<div class="print-controls"><button type="button" onclick="window.print()">Print</button></div>
@php($address = collect([$receipt['address'] ?? null, $receipt['city'] ?? null, $receipt['country'] ?? null])->filter()->join(', '))
<main class="receipt">
    @if ($format === 'a4')
        <header class="invoice-head"><div><div class="shop">{{ $receipt['shop_name'] }}</div><div>{{ $receipt['gst_label'] }}: {{ $receipt['tax_number'] ?: 'Not configured' }}</div>@if (($receipt['show_address'] ?? true) && $address)<div>{{ $address }}</div>@endif @if (($receipt['show_phone'] ?? true) && ($receipt['phone'] ?? null))<div>{{ $receipt['phone'] }}</div>@endif</div><div class="invoice-title"><h1>TAX INVOICE</h1><strong>{{ $sale->sale_number }}</strong><div>{{ $sale->completed_at?->format('d M Y, h:i A') }}</div></div></header>
    @else
        <header class="center"><div class="shop">{{ $receipt['shop_name'] }}</div><div class="muted">{{ $receipt['gst_label'] }}: {{ $receipt['tax_number'] ?: 'Not configured' }}</div>@if (($receipt['show_address'] ?? true) && $address)<div class="muted">{{ $address }}</div>@endif @if (($receipt['show_phone'] ?? true) && ($receipt['phone'] ?? null))<div class="muted">{{ $receipt['phone'] }}</div>@endif</header>
    @endif
    @if ($receipt['header'] ?? null)<div class="center muted">{!! nl2br(e($receipt['header'])) !!}</div>@endif
    <div class="watermark">REPRINT #{{ $printEvent->reprint_number }}</div>
    <div class="center muted">Reprinted by {{ $printEvent->printer?->name ?? 'System' }} on {{ $printEvent->printed_at->format('d M Y, h:i A') }}</div>
    <div class="rule"></div><div>Receipt: {{ $sale->sale_number }}</div><div>Date: {{ $sale->completed_at?->format('d M Y, h:i A') }}</div><div>Branch: {{ $sale->branch?->name }}</div><div>Customer: {{ $sale->customer?->name ?? 'Walk-in Customer' }}</div><div class="rule"></div>
    <table><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead><tbody>@foreach ($sale->items as $item)<tr><td>{{ $item->description }}<br><small>{{ $item->product?->sku }}</small></td><td class="num">{{ number_format((float) $item->quantity, 2) }}</td><td class="num">{{ $sale->currency }} {{ number_format((float) $item->line_total, 2) }}</td></tr><tr class="tax"><td colspan="2">GST {{ number_format((float) $item->tax_rate, 2) }}%</td><td class="num">{{ $sale->currency }} {{ number_format((float) $item->tax_amount, 2) }}</td></tr>@endforeach</tbody></table>
    <div class="rule"></div><div class="totals"><div><span>Subtotal</span><span>{{ $sale->currency }} {{ number_format((float) $sale->subtotal, 2) }}</span></div><div><span>GST</span><span>{{ $sale->currency }} {{ number_format((float) $sale->tax_total, 2) }}</span></div>@if ((float) $sale->discount_total > 0)<div><span>Discount</span><span>-{{ $sale->currency }} {{ number_format((float) $sale->discount_total, 2) }}</span></div>@endif<div class="grand"><span>Total</span><span>{{ $sale->currency }} {{ number_format((float) $sale->grand_total, 2) }}</span></div></div>
    <div class="rule"></div><div class="payments">@foreach ($sale->payments as $payment)<div><span>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }} paid</span><span>{{ $sale->currency }} {{ number_format((float) $payment->amount, 2) }}</span></div>@if ($payment->amount_tendered !== null)<div><span>Cash received</span><span>{{ $sale->currency }} {{ number_format((float) $payment->amount_tendered, 2) }}</span></div><div><strong>Change given</strong><strong>{{ $sale->currency }} {{ number_format((float) $payment->change_due, 2) }}</strong></div>@endif @endforeach</div>
    <footer class="footer">{!! $receipt['footer'] ? nl2br(e($receipt['footer'])) : 'Thank you for shopping with us.' !!}</footer>
</main>
</body>
</html>
