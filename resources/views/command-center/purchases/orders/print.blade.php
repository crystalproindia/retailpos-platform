<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->po_number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-slate-950 print:p-0">
    <main class="mx-auto max-w-4xl">
        <div class="flex items-start justify-between border-b border-slate-300 pb-6">
            <div>
                <h1 class="text-2xl font-semibold">Purchase Order</h1>
                <p class="mt-1 text-slate-500">{{ $order->po_number }}</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-medium">{{ $order->supplier?->name }}</p>
                <p>{{ $order->order_date?->format('d M Y') }}</p>
            </div>
        </div>
        <table class="mt-8 w-full text-sm">
            <thead><tr class="border-b text-left"><th class="py-2">Product</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Total</th></tr></thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b"><td class="py-3">{{ $item->product_name_snapshot }}</td><td class="py-3 text-right">{{ $item->ordered_quantity }}</td><td class="py-3 text-right">₹{{ number_format((float) $item->unit_price, 2) }}</td><td class="py-3 text-right">₹{{ number_format((float) $item->line_total, 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3" class="py-4 text-right font-semibold">Grand total</td><td class="py-4 text-right font-semibold">₹{{ number_format((float) $order->grand_total, 2) }}</td></tr></tfoot>
        </table>
    </main>
</body>
</html>
