<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #64748b; }
        .header { display: flex; justify-content: space-between; margin-bottom: 22px; }
        .logo { display: block; max-width: 150px; max-height: 42px; width: auto; height: auto; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 7px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .strong { font-weight: bold; border-top: 2px solid #94a3b8; }
        .net { font-size: 13px; font-weight: bold; color: #0f766e; }
        .note { margin-top: 22px; padding: 10px; background: #f8fafc; color: #475569; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            @if ($logo ?? null)
                <img class="logo" src="{{ $logo }}" alt="{{ $company->legal_name ?: $company->name }}">
            @endif
            <h1>Profit &amp; Loss Statement</h1>
            <p class="muted">{{ $company->legal_name ?: $company->name }}</p>
        </div>
        <div class="right">
            <strong>{{ $scope }}</strong><br>
            <span class="muted">{{ $report['period']['from'] }} to {{ $report['period']['to'] }}</span>
        </div>
    </div>

    <table>
        <tr><td colspan="2"><strong>Revenue</strong></td></tr>
        <tr><td>Gross Sales</td><td class="right">₹{{ number_format($report['gross_sales'] / 100, 2) }}</td></tr>
        <tr><td>Less: Returns / Credits</td><td class="right">₹{{ number_format($report['returns_credits'] / 100, 2) }}</td></tr>
        <tr class="strong"><td>Net Sales</td><td class="right">₹{{ number_format($report['net_sales'] / 100, 2) }}</td></tr>
        <tr><td colspan="2"><strong>Cost of Sales</strong></td></tr>
        <tr><td>COGS</td><td class="right">₹{{ number_format($report['cogs'] / 100, 2) }}</td></tr>
        <tr class="strong"><td>Gross Profit ({{ $report['gross_margin_percent'] ?? '—' }}%)</td><td class="right">₹{{ number_format($report['gross_profit'] / 100, 2) }}</td></tr>
        <tr><td colspan="2"><strong>Operating Expenses</strong></td></tr>
        @foreach ($report['operating_expenses_by_category'] as $row)
            <tr><td>{{ $row['category'] }}</td><td class="right">₹{{ number_format($row['amount'] / 100, 2) }}</td></tr>
        @endforeach
        <tr class="strong"><td>Total Operating Expenses</td><td class="right">₹{{ number_format($report['operating_expenses'] / 100, 2) }}</td></tr>
        <tr class="strong"><td>Operating Profit ({{ $report['operating_margin_percent'] ?? '—' }}%)</td><td class="right">₹{{ number_format($report['operating_profit'] / 100, 2) }}</td></tr>
        <tr><td>Other Income</td><td class="right">₹{{ number_format($report['other_income'] / 100, 2) }}</td></tr>
        <tr><td>Other Expenses</td><td class="right">₹{{ number_format($report['other_expenses'] / 100, 2) }}</td></tr>
        <tr class="net"><td>NET PROFIT ({{ $report['net_margin_percent'] ?? '—' }}%)</td><td class="right">₹{{ number_format($report['net_profit'] / 100, 2) }}</td></tr>
    </table>

    @if ($report['has_unallocated_company_expenses'])
        <p class="note">Outlet profit excludes unallocated company-wide expenses.</p>
    @endif
    <p class="note">Net Profit is based on financial transactions recorded in RetailPOS. External or unrecorded expenses are not included.</p>
</body>
</html>
