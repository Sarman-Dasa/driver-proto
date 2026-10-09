<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 30px 15px;
            line-height: 1.5;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #4f46e5;
            color: #ffffff;
            padding: 28px 32px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        .invoice-banner {
            font-size: 13px;
            color: #e0e7ff;
            margin-top: 4px;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .custom-message {
            background-color: #f8fafc;
            border-left: 4px solid #4f46e5;
            padding: 14px 18px;
            font-size: 14px;
            color: #334155;
            margin: 16px 0;
            border-radius: 0 6px 6px 0;
            white-space: pre-line;
        }
        .invoice-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .card-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .card-row.total-row {
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            margin-top: 10px;
            margin-bottom: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .card-label {
            color: #64748b;
        }
        .card-value {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }
        .attachment-note {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            padding: 12px;
            background-color: #f1f5f9;
            border-radius: 6px;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="company-name">{{ $invoice->company->name }}</h1>
            <div class="invoice-banner">Invoice {{ $invoice->invoice_number }}</div>
        </div>

        <div class="content">
            @php
                $snapshot = $invoice->customer_snapshot ?? [];
                $customerName = $snapshot['name'] ?? $invoice->customer?->name ?? 'Valued Customer';
            @endphp
            <div class="greeting">Hello {{ $customerName }},</div>

            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                Please find attached invoice <strong>{{ $invoice->invoice_number }}</strong> issued by <strong>{{ $invoice->company->name }}</strong>.
            </p>

            @if(!empty($customMessage))
                <div class="custom-message">
                    {{ $customMessage }}
                </div>
            @endif

            <table style="width: 100%; border-collapse: collapse; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin: 20px 0;">
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; font-size: 13px;">Invoice Number:</td>
                    <td style="padding: 12px 16px; font-weight: 600; color: #0f172a; text-align: right; font-size: 13px;">{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 16px; color: #64748b; font-size: 13px;">Issue Date:</td>
                    <td style="padding: 8px 16px; font-weight: 600; color: #0f172a; text-align: right; font-size: 13px;">{{ $invoice->issue_date->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 16px; color: #64748b; font-size: 13px;">Due Date:</td>
                    <td style="padding: 8px 16px; font-weight: 600; color: #0f172a; text-align: right; font-size: 13px;">{{ $invoice->due_date->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-top: 1px solid #cbd5e1; font-weight: 700; color: #0f172a; font-size: 15px;">Amount Due:</td>
                    <td style="padding: 12px 16px; border-top: 1px solid #cbd5e1; font-weight: 800; color: #4f46e5; text-align: right; font-size: 16px;">
                        ${{ number_format($invoice->balance_due / 100, 2) }} {{ $invoice->currency }}
                    </td>
                </tr>
            </table>

            @if(!empty($invoice->payment_token) && $invoice->balance_due > 0)
                <div style="text-align: center; margin: 28px 0;">
                    <a href="{{ rtrim(config('app.url'), '/') . '/pay/' . $invoice->payment_token }}"
                       style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.25);">
                        Pay Invoice Online &rarr;
                    </a>
                </div>
            @endif

            <div class="attachment-note">
                📎 <span>A PDF copy of this invoice (<strong>Invoice-{{ $invoice->invoice_number }}.pdf</strong>) has been attached to this email.</span>
            </div>
        </div>

        <div class="footer">
            Sent via BillFlow on behalf of {{ $invoice->company->name }}. If you have any questions, please reply to {{ $invoice->company->email ?? 'support@billflow.test' }}.
        </div>
    </div>
</body>
</html>
