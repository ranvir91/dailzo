<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StoreSetting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the GST tax invoice for an order (modeled on the reference Blinkit
 * invoice layout) and renders it to a PDF.
 *
 * Tax split: every order_item carries its own frozen tax_rate (snapshotted
 * at order time — see OrderController::store), the combined GST rate for
 * that product's category. When the delivery address's state matches the
 * seller's own state this is split evenly into CGST+SGST; otherwise it's
 * charged as a single IGST line. Delivery charges are shown as their own
 * taxed line (assumed 18% GST, the standard rate for delivery/logistics
 * services in India — store_settings has no separate rate for this).
 */
class InvoiceService
{
    private const DELIVERY_TAX_RATE = 18.0;

    public function invoiceNumber(Order $order): string
    {
        return 'DLZ-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    }

    public function buildData(Order $order): array
    {
        $order->loadMissing(['items.product.category', 'address', 'user', 'coupon']);
        $settings = StoreSetting::current();

        $sellerState = trim((string) $settings->business_state);
        $buyerState = trim((string) ($order->address->state ?? ''));
        $isIntraState = $sellerState === '' || $buyerState === ''
            || strcasecmp($sellerState, $buyerState) === 0;

        $lines = [];
        $sr = 1;
        $subtotal = 0.0;
        $cgstTotal = 0.0;
        $sgstTotal = 0.0;
        $igstTotal = 0.0;
        $taxableTotal = 0.0;

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            $taxRate = (float) $item->tax_rate;
            $unitTaxable = (float) $item->base_price;
            $unitTax = (float) $item->tax_amount;
            $lineTaxable = round($unitTaxable * $qty, 2);
            $lineTax = round($unitTax * $qty, 2);
            $lineTotal = round((float) $item->price * $qty, 2);
            $lineMrp = (float) $item->mrp;
            $lineDiscount = $lineMrp > 0 ? max(0, round(($lineMrp - (float) $item->price) * $qty, 2)) : 0;

            $cgst = $sgst = $igst = 0.0;
            if ($isIntraState) {
                $cgst = round($lineTax / 2, 2);
                $sgst = round($lineTax - $cgst, 2);
            } else {
                $igst = $lineTax;
            }

            $lines[] = [
                'sr' => $sr++,
                'code' => $item->product->sku ?: $item->product->item_code ?: (string) $item->product_id,
                'description' => $item->product->name
                    .($item->product->hsn_sac_code ? " (HSN {$item->product->hsn_sac_code})" : ''),
                'mrp' => $lineMrp > 0 ? $lineMrp : null,
                'discount' => $lineDiscount > 0 ? $lineDiscount : null,
                'qty' => $qty,
                'taxableValue' => $lineTaxable,
                'taxRate' => $taxRate,
                'cgstRate' => $isIntraState ? round($taxRate / 2, 2) : 0,
                'cgst' => $cgst,
                'sgstRate' => $isIntraState ? round($taxRate / 2, 2) : 0,
                'sgst' => $sgst,
                'igstRate' => $isIntraState ? 0 : $taxRate,
                'igst' => $igst,
                'total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
            $taxableTotal += $lineTaxable;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
        }

        $deliveryCharges = (float) $order->delivery_charges;
        $deliveryLine = null;
        if ($deliveryCharges > 0) {
            $deliveryTaxable = round($deliveryCharges / (1 + self::DELIVERY_TAX_RATE / 100), 2);
            $deliveryTax = round($deliveryCharges - $deliveryTaxable, 2);
            $dCgst = $dSgst = $dIgst = 0.0;
            if ($isIntraState) {
                $dCgst = round($deliveryTax / 2, 2);
                $dSgst = round($deliveryTax - $dCgst, 2);
            } else {
                $dIgst = $deliveryTax;
            }

            $deliveryLine = [
                'description' => 'Delivery and other charges',
                'taxableValue' => $deliveryTaxable,
                'cgstRate' => $isIntraState ? self::DELIVERY_TAX_RATE / 2 : 0,
                'cgst' => $dCgst,
                'sgstRate' => $isIntraState ? self::DELIVERY_TAX_RATE / 2 : 0,
                'sgst' => $dSgst,
                'igstRate' => $isIntraState ? 0 : self::DELIVERY_TAX_RATE,
                'igst' => $dIgst,
                'total' => $deliveryCharges,
            ];

            $subtotal += $deliveryCharges;
            $taxableTotal += $deliveryTaxable;
            $cgstTotal += $dCgst;
            $sgstTotal += $dSgst;
            $igstTotal += $dIgst;
        }

        $cgstTotal = round($cgstTotal, 2);
        $sgstTotal = round($sgstTotal, 2);
        $igstTotal = round($igstTotal, 2);
        $taxableTotal = round($taxableTotal, 2);
        $subtotal = round($subtotal, 2);
        $discount = (float) $order->discount_amount;
        $grandTotal = (float) $order->total;

        $signaturePath = public_path('images/invoice/signature.png');

        return [
            'order' => $order,
            'invoiceNumber' => $this->invoiceNumber($order),
            'invoiceDate' => $order->created_at,
            'isIntraState' => $isIntraState,
            'seller' => $settings,
            'buyerName' => $order->user->name ?? 'Customer',
            'buyerAddress' => $order->address,
            'placeOfSupply' => $buyerState ?: $sellerState,
            'lines' => $lines,
            'deliveryLine' => $deliveryLine,
            'subtotal' => $subtotal,
            'taxableTotal' => $taxableTotal,
            'cgstTotal' => $cgstTotal,
            'sgstTotal' => $sgstTotal,
            'igstTotal' => $igstTotal,
            'couponCode' => $order->coupon->code ?? null,
            'discount' => $discount,
            'grandTotal' => $grandTotal,
            'amountInWords' => $this->amountInWords($grandTotal),
            'signaturePath' => file_exists($signaturePath) ? $signaturePath : null,
        ];
    }

    public function render(Order $order)
    {
        $data = $this->buildData($order);

        return Pdf::loadView('invoices.order', $data)->setPaper('a4');
    }

    /** "₹1,260.00" -> "One Thousand Two Hundred And Sixty Rupees Only". Indian (lakh/crore) grouping. */
    public function amountInWords(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = $this->integerToWords($rupees).' Rupees';
        if ($paise > 0) {
            $words .= ' And '.$this->integerToWords($paise).' Paise';
        }

        return $words.' Only';
    }

    private function integerToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
            'Eighteen', 'Nineteen',
        ];
        $tens = [
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
        ];

        $twoDigits = function (int $n) use ($ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }

            return trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        };

        $threeDigits = function (int $n) use ($ones, $twoDigits): string {
            if ($n >= 100) {
                $rest = $twoDigits($n % 100);

                return trim($ones[intdiv($n, 100)].' Hundred'.($rest ? ' '.$rest : ''));
            }

            return $twoDigits($n);
        };

        $crore = intdiv($number, 10000000);
        $lakh = intdiv($number % 10000000, 100000);
        $thousand = intdiv($number % 100000, 1000);
        $hundred = $number % 1000;

        $parts = [];
        if ($crore > 0) {
            $parts[] = $threeDigits($crore).' Crore';
        }
        if ($lakh > 0) {
            $parts[] = $threeDigits($lakh).' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = $threeDigits($thousand).' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = $threeDigits($hundred);
        }

        return implode(' ', $parts);
    }
}
