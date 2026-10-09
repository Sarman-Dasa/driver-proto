<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 36px 36px 45px 36px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .brand-name {
            font-size: 24px;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .invoice-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .invoice-number {
            font-size: 14px;
            font-weight: 700;
            color: #4f46e5;
            text-align: right;
            margin-top: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 9999px;
            margin-top: 6px;
        }
        .status-draft { background-color: #f1f5f9; color: #475569; }
        .status-sent { background-color: #e0f2fe; color: #0284c7; }
        .status-partially_paid { background-color: #fef3c7; color: #d97706; }
        .status-paid { background-color: #dcfce7; color: #16a34a; }
        .status-overdue { background-color: #fee2e2; color: #dc2626; }
        .status-cancelled { background-color: #f3f4f6; color: #6b7280; }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 16px;
        }
        .party-box {
            vertical-align: top;
            width: 50%;
            padding: 12px 16px;
        }
        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .party-name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .party-detail {
            font-size: 11px;
            color: #475569;
            line-height: 1.4;
        }
        .dates-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .date-cell {
            padding: 8px 12px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 25%;
        }
        .date-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
        }
        .date-value {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 2px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #4f46e5;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }
        .items-table th.text-center,
        .items-table td.text-center {
            text-align: center;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .item-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 11px;
        }
        .item-desc {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .notes-column {
            width: 58%;
            vertical-align: top;
            padding-right: 20px;
        }
        .totals-column {
            width: 42%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
            font-size: 11px;
        }
        .totals-table td.label {
            color: #64748b;
            font-weight: 500;
        }
        .totals-table td.value {
            text-align: right;
            color: #0f172a;
            font-weight: 600;
        }
        .grand-total-row td {
            border-top: 2px solid #4f46e5;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .grand-total-row td.label {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }
        .grand-total-row td.value {
            font-size: 16px;
            font-weight: 800;
            color: #4f46e5;
        }
        .balance-due-row td {
            background-color: #f1f5f9;
            border-radius: 4px;
            padding: 6px 8px;
            font-weight: 700;
        }

        .notes-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .notes-card-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 4px;
        }
        .notes-card-text {
            font-size: 10px;
            color: #64748b;
            white-space: pre-line;
            line-height: 1.4;
        }

        .footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    {{-- Header with Brand & Invoice Number --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                <h1 class="brand-name">{{ $company->name }}</h1>
                <div class="brand-subtitle">Powered by BillFlow SaaS</div>
            </td>
            <td style="vertical-align: top; width: 45%; text-align: right;">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div style="text-align: right;">
                    <span class="status-badge status-{{ $invoice->status->value }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Party snapshots: From (Company) & Bill To (Customer Snapshot) --}}
    <table class="meta-table">
        <tr>
            <td class="party-box">
                <div class="section-label">FROM</div>
                <div class="party-name">{{ $company->name }}</div>
                @if($company->address_line1 || $company->address)
                    <div class="party-detail">{{ $company->address_line1 ?? $company->address }}</div>
                @endif
                @if($company->city || $company->state || $company->postal_code)
                    <div class="party-detail">
                        {{ implode(', ', array_filter([$company->city, $company->state, $company->postal_code])) }}
                    </div>
                @endif
                @if($company->country)
                    <div class="party-detail">{{ $company->country }}</div>
                @endif
                @if($company->email)
                    <div class="party-detail">Email: {{ $company->email }}</div>
                @endif
                @if($company->phone)
                    <div class="party-detail">Phone: {{ $company->phone }}</div>
                @endif
                @if($company->tax_number)
                    <div class="party-detail">Tax ID: {{ $company->tax_number }}</div>
                @endif
            </td>
            <td class="party-box">
                <div class="section-label">BILL TO</div>
                @php
                    $snapshot = $invoice->customer_snapshot ?? [];
                    $custName = $snapshot['name'] ?? $invoice->customer?->name ?? 'Valued Customer';
                    $custCompany = $snapshot['company_name'] ?? null;
                    $custEmail = $snapshot['email'] ?? $invoice->customer?->email ?? null;
                    $custAddress = $snapshot['billing_address'] ?? null;
                    $custCity = $snapshot['billing_city'] ?? null;
                    $custState = $snapshot['billing_state'] ?? null;
                    $custPostal = $snapshot['billing_postal_code'] ?? null;
                    $custCountry = $snapshot['billing_country'] ?? null;
                    $custTax = $snapshot['tax_number'] ?? null;
                @endphp
                <div class="party-name">{{ $custName }}</div>
                @if($custCompany)
                    <div class="party-detail" style="font-weight: 600;">{{ $custCompany }}</div>
                @endif
                @if($custAddress)
                    <div class="party-detail">{{ $custAddress }}</div>
                @endif
                @if($custCity || $custState || $custPostal)
                    <div class="party-detail">
                        {{ implode(', ', array_filter([$custCity, $custState, $custPostal])) }}
                    </div>
                @endif
                @if($custCountry)
                    <div class="party-detail">{{ $custCountry }}</div>
                @endif
                @if($custEmail)
                    <div class="party-detail">Email: {{ $custEmail }}</div>
                @endif
                @if($custTax)
                    <div class="party-detail">Tax ID: {{ $custTax }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Dates & Currency Information --}}
    <table class="dates-box">
        <tr>
            <td class="date-cell">
                <div class="date-label">Issue Date</div>
                <div class="date-value">{{ $invoice->issue_date->format('M d, Y') }}</div>
            </td>
            <td style="width: 10px;"></td>
            <td class="date-cell">
                <div class="date-label">Due Date</div>
                <div class="date-value">{{ $invoice->due_date->format('M d, Y') }}</div>
            </td>
            <td style="width: 10px;"></td>
            <td class="date-cell">
                <div class="date-label">Currency</div>
                <div class="date-value">{{ $invoice->currency }}</div>
            </td>
            <td style="width: 10px;"></td>
            <td class="date-cell">
                <div class="date-label">Balance Due</div>
                <div class="date-value" style="color: #4f46e5; font-weight: 700;">
                    ${{ number_format($invoice->balance_due / 100, 2) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Line Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Item & Description</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 14%;" class="text-right">Unit Price</th>
                <th style="width: 11%;" class="text-right">Tax</th>
                <th style="width: 15%;" class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
                <tr>
                    <td class="text-center" style="color: #94a3b8;">{{ $index + 1 }}</td>
                    <td>
                        <div class="item-title">{{ $item->product_name }}</div>
                        @if($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}
                        @if($item->unit)
                            <span style="color: #94a3b8; font-size: 9px;">{{ $item->unit }}</span>
                        @endif
                    </td>
                    <td class="text-right">${{ number_format($item->unit_price / 100, 2) }}</td>
                    <td class="text-right">
                        @if((float) $item->tax_rate > 0)
                            {{ (float) $item->tax_rate }}%
                            <div style="font-size: 9px; color: #94a3b8;">+${{ number_format($item->tax_amount / 100, 2) }}</div>
                        @else
                            0%
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: 600;">
                        ${{ number_format($item->total / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Bottom Summary & Notes --}}
    <table class="summary-wrapper">
        <tr>
            <td class="notes-column">
                @if($invoice->notes)
                    <div class="notes-card">
                        <div class="notes-card-title">Notes</div>
                        <div class="notes-card-text">{{ e($invoice->notes) }}</div>
                    </div>
                @endif

                @if($invoice->terms)
                    <div class="notes-card">
                        <div class="notes-card-title">Terms & Conditions</div>
                        <div class="notes-card-text">{{ e($invoice->terms) }}</div>
                    </div>
                @endif
            </td>
            <td class="totals-column">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">${{ number_format($invoice->subtotal / 100, 2) }}</td>
                    </tr>
                    @if($invoice->discount_amount > 0)
                        <tr>
                            <td class="label">
                                Discount
                                @if($invoice->discount_type->value === 'percentage')
                                    ({{ (float) $invoice->discount_value }}%)
                                @endif
                            </td>
                            <td class="value" style="color: #16a34a;">-${{ number_format($invoice->discount_amount / 100, 2) }}</td>
                        </tr>
                    @endif
                    @if($invoice->tax_amount > 0)
                        <tr>
                            <td class="label">Tax</td>
                            <td class="value">+${{ number_format($invoice->tax_amount / 100, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total-row">
                        <td class="label">Total Due</td>
                        <td class="value">${{ number_format($invoice->total_amount / 100, 2) }}</td>
                    </tr>
                    @if($invoice->amount_paid > 0)
                        <tr>
                            <td class="label">Amount Paid</td>
                            <td class="value" style="color: #16a34a;">-${{ number_format($invoice->amount_paid / 100, 2) }}</td>
                        </tr>
                        <tr class="balance-due-row">
                            <td class="label">Balance Due</td>
                            <td class="value">${{ number_format($invoice->balance_due / 100, 2) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your business. For billing inquiries, contact {{ $company->email ?? 'support@billflow.test' }}.
    </div>

    {{-- Page number script for Dompdf --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $size = 8;
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 24;
            $pdf->page_text($x, $y, $text, $font, $size, [0.45, 0.50, 0.55]);
        }
    </script>
</body>
</html>
