<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; margin: 0; }
        .wrap { border: 1px solid #999; padding: 12px; }
        table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        h1 { font-size: 20px; margin: 0; text-align: right; }
        .section-title { font-weight: bold; font-size: 11px; margin-bottom: 2px; }
        .meta-table td { padding: 1px 0; }
        .meta-table td.label { width: 85px; color: #444; }
        .box { border: 1px solid #ccc; padding: 8px; margin-top: 10px; }
        .items-table { margin-top: 10px; }
        .items-table th, .items-table td {
            border: 1px solid #999; padding: 4px; font-size: 9px; text-align: center;
        }
        .items-table th { background: #f0f0f0; }
        .items-table td.desc { text-align: left; }
        .totals-row td { font-weight: bold; background: #f7f7f7; }
        .summary-table { width: 260px; margin-left: auto; margin-top: 10px; }
        .summary-table td { padding: 2px 4px; font-size: 10px; }
        .summary-table .grand td { font-weight: bold; font-size: 11px; border-top: 1px solid #999; }
        .words { margin-top: 10px; font-size: 10px; }
        .footer-table { margin-top: 16px; }
        .footer-table td { vertical-align: top; }
        .signature img { height: 50px; }
        .terms { margin-top: 14px; font-size: 8.5px; color: #444; }
        .terms li { margin-bottom: 2px; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="section-title">{{ $seller->business_name ?: config('app.name') }}</div>
                <div>{{ $seller->business_address }}</div>
                <div>{{ trim(($seller->business_state ?? '').' '.($seller->business_pincode ?? ''), ' ') }}</div>
            </td>
            <td style="width: 40%;">
                <h1>Tax Invoice</h1>
                <table class="meta-table" style="margin-top: 6px;">
                    <tr><td class="label">Invoice No.</td><td>: {{ $invoiceNumber }}</td></tr>
                    <tr><td class="label">Invoice Date</td><td>: {{ $invoiceDate?->format('d-M-Y') }}</td></tr>
                    <tr><td class="label">Order Id</td><td>: {{ $order->id }}</td></tr>
                    <tr><td class="label">Place of Supply</td><td>: {{ $placeOfSupply ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta-table" style="margin-top: 8px;">
        @if($seller->gstin) <tr><td class="label">GSTIN</td><td>: {{ $seller->gstin }}</td></tr> @endif
        @if($seller->fssai_license) <tr><td class="label">FSSAI No.</td><td>: {{ $seller->fssai_license }}</td></tr> @endif
        @if($seller->cin) <tr><td class="label">CIN</td><td>: {{ $seller->cin }}</td></tr> @endif
        @if($seller->pan) <tr><td class="label">PAN</td><td>: {{ $seller->pan }}</td></tr> @endif
    </table>

    <div class="box">
        <div class="section-title">Invoice To</div>
        <div>Name: {{ $buyerName }}</div>
        @if($buyerAddress)
            <div>Address: {{ trim($buyerAddress->line1.' '.$buyerAddress->line2) }}, {{ $buyerAddress->city }}</div>
            <div>Pincode: {{ $buyerAddress->pincode }}</div>
            <div>State: {{ $buyerAddress->state ?: '-' }}</div>
        @endif
    </div>

    <table class="items-table">
        <thead>
        <tr>
            <th>Sr</th>
            <th>Code</th>
            <th style="width: 22%;">Item Description</th>
            <th>MRP</th>
            <th>Discount</th>
            <th>Qty</th>
            <th>Taxable Value</th>
            @if($isIntraState)
                <th>CGST %</th>
                <th>CGST</th>
                <th>SGST %</th>
                <th>SGST</th>
            @else
                <th>IGST %</th>
                <th>IGST</th>
            @endif
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line['sr'] }}</td>
                <td>{{ $line['code'] }}</td>
                <td class="desc">{{ $line['description'] }}</td>
                <td>{{ $line['mrp'] !== null ? number_format($line['mrp'], 2) : '-' }}</td>
                <td>{{ $line['discount'] !== null ? number_format($line['discount'], 2) : '-' }}</td>
                <td>{{ $line['qty'] }}</td>
                <td>{{ number_format($line['taxableValue'], 2) }}</td>
                @if($isIntraState)
                    <td>{{ number_format($line['cgstRate'], 2) }}</td>
                    <td>{{ number_format($line['cgst'], 2) }}</td>
                    <td>{{ number_format($line['sgstRate'], 2) }}</td>
                    <td>{{ number_format($line['sgst'], 2) }}</td>
                @else
                    <td>{{ number_format($line['igstRate'], 2) }}</td>
                    <td>{{ number_format($line['igst'], 2) }}</td>
                @endif
                <td>{{ number_format($line['total'], 2) }}</td>
            </tr>
        @endforeach
        @if($deliveryLine)
            <tr>
                <td>-</td>
                <td>-</td>
                <td class="desc">{{ $deliveryLine['description'] }}</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>{{ number_format($deliveryLine['taxableValue'], 2) }}</td>
                @if($isIntraState)
                    <td>{{ number_format($deliveryLine['cgstRate'], 2) }}</td>
                    <td>{{ number_format($deliveryLine['cgst'], 2) }}</td>
                    <td>{{ number_format($deliveryLine['sgstRate'], 2) }}</td>
                    <td>{{ number_format($deliveryLine['sgst'], 2) }}</td>
                @else
                    <td>{{ number_format($deliveryLine['igstRate'], 2) }}</td>
                    <td>{{ number_format($deliveryLine['igst'], 2) }}</td>
                @endif
                <td>{{ number_format($deliveryLine['total'], 2) }}</td>
            </tr>
        @endif
        <tr class="totals-row">
            <td colspan="6">Total</td>
            <td>{{ number_format($taxableTotal, 2) }}</td>
            @if($isIntraState)
                <td></td>
                <td>{{ number_format($cgstTotal, 2) }}</td>
                <td></td>
                <td>{{ number_format($sgstTotal, 2) }}</td>
            @else
                <td></td>
                <td>{{ number_format($igstTotal, 2) }}</td>
            @endif
            <td>{{ number_format($subtotal, 2) }}</td>
        </tr>
        </tbody>
    </table>

    <table class="summary-table">
        <tr><td>Subtotal</td><td style="text-align: right;">Rs. {{ number_format($subtotal, 2) }}</td></tr>
        @if($discount > 0)
            <tr>
                <td>Coupon discount{{ $couponCode ? " ({$couponCode})" : '' }}</td>
                <td style="text-align: right;">-Rs. {{ number_format($discount, 2) }}</td>
            </tr>
        @endif
        <tr class="grand"><td>Grand Total</td><td style="text-align: right;">Rs. {{ number_format($grandTotal, 2) }}</td></tr>
    </table>

    <div class="words">Amount in Words: {{ $amountInWords }}</div>

    <table class="footer-table">
        <tr>
            <td style="width: 60%;">
                <div>{{ $seller->business_name ?: config('app.name') }}</div>
                @if($seller->gstin) <div>GSTIN: {{ $seller->gstin }}</div> @endif
                @if($seller->pan) <div>PAN: {{ $seller->pan }}</div> @endif
                <div style="margin-top: 6px;">Whether the tax is payable on reverse charge: No</div>
            </td>
            <td style="width: 40%; text-align: right;" class="signature">
                @if($signaturePath)
                    <img src="{{ $signaturePath }}" alt="Signature">
                @endif
                <div>Authorised Signatory</div>
            </td>
        </tr>
    </table>

    <div class="terms">
        <div class="section-title">Terms &amp; Conditions</div>
        <ol>
            <li>This is a system generated invoice for the order placed on the Dailzo app.</li>
            <li>For any issues or queries regarding this order, please contact support through the app.</li>
            <li>Prices shown are inclusive of all applicable taxes.</li>
        </ol>
    </div>
</div>
</body>
</html>
