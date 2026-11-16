<?php

namespace App\Services;

use App\Models\SellerOrder;

class OfficialInvoicePdfService
{
    public function generatePdfBinary(SellerOrder $sellerOrder): string
    {
        $sellerOrder->loadMissing([
            'order',
            'buyer',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'payment',
            'extra',
            'shipment',
        ]);

        $invoiceNo = 'INV-' . str_replace('SO-', '', (string) $sellerOrder->seller_order_number);
        $deliverySnap = (array) ($sellerOrder->delivery_address_snapshot ?? []);
        $pickupSnap = (array) ($sellerOrder->pickup_address_snapshot ?? []);
        $extra = $sellerOrder->extra;
        $payMethod = $sellerOrder->effective_payment_method;
        $payStatus = strtoupper((string) $sellerOrder->payment_status);

        $originalSub = (float) ($extra?->original_subtotal_amount ?: $sellerOrder->subtotal_amount);
        $storeDiscount = (float) ($extra?->store_voucher_discount ?? 0);
        $coinsDiscount = (float) ($extra?->coins_discount_amount ?? 0);
        $coinsUsed = (int) ($extra?->coins_used ?? 0);
        $rawPlatformDiscount = (float) ($extra?->platform_voucher_discount ?? 0);
        $netSub = (float) $sellerOrder->subtotal_amount;

        $platformDiscount = 0.0;
        if ($extra && ! empty($extra->platform_voucher_code)) {
            if ($originalSub > 0 && abs(round($originalSub - $storeDiscount - $rawPlatformDiscount - $coinsDiscount, 2) - round($netSub, 2)) < 0.02) {
                $platformDiscount = round($rawPlatformDiscount, 2);
            } else {
                $platformDiscount = max(0.0, round($rawPlatformDiscount - $coinsDiscount, 2));
            }
        }

        $shippingDiscount = (float) ($extra?->shipping_voucher_discount ?? 0);
        $originalShip = (float) ($extra?->original_shipping_fee ?: $sellerOrder->shipping_fee);
        $netShip = (float) $sellerOrder->shipping_fee;
        $grandTotal = (float) $sellerOrder->total_amount;

        $storeName = $sellerOrder->sellerProfile?->business_name
            ?? ($pickupSnap['business_name'] ?? 'Seller Store');
        $storeAddress = $pickupSnap['full_address'] ?? '';
        $storeContact = $pickupSnap['contact_no'] ?? '';

        $buyerName = $deliverySnap['recipient_name']
            ?? ($sellerOrder->buyer?->full_name ?? 'Buyer');
        $buyerAddress = $deliverySnap['full_address'] ?? '';
        $buyerPhone = $deliverySnap['phone_number'] ?? ($sellerOrder->buyer?->contact_no ?? '');

        $issuedAt = ($sellerOrder->completed_at ?? $sellerOrder->delivered_at ?? $sellerOrder->created_at)
            ?->timezone('Asia/Manila')
            ?->format('M d, Y h:i A') ?? now()->timezone('Asia/Manila')->format('M d, Y h:i A');

        $commands = [];

        $commands[] = '0.365 0.192 0.251 rg';
        $commands[] = '36 742 540 58 re f';

        $commands[] = $this->textCmd(50, 774, 16, 'F2', 'STORKIA OFFICIAL ELECTRONIC INVOICE', [1, 1, 1]);
        $commands[] = $this->textCmd(50, 756, 9, 'F1', 'Invoice No: ' . $invoiceNo . '   |   Order No: ' . $sellerOrder->seller_order_number, [0.965, 0.847, 0.741]);
        $commands[] = $this->textCmd(400, 774, 10, 'F2', $payMethod . ' (' . $payStatus . ')', [1, 1, 1]);
        $commands[] = $this->textCmd(400, 756, 8.5, 'F1', 'Issued: ' . $issuedAt, [1, 1, 1]);

        $y = 716;
        $commands[] = '0.96 0.95 0.94 rg';
        $commands[] = "36 " . ($y - 54) . " 262 66 re f";
        $commands[] = "314 " . ($y - 54) . " 262 66 re f";

        $commands[] = $this->textCmd(46, $y - 2, 8, 'F2', 'SOLD & FULFILLED BY', [0.5, 0.45, 0.45]);
        $commands[] = $this->textCmd(46, $y - 16, 10, 'F2', $this->truncate($storeName, 42), [0.15, 0.14, 0.14]);
        $commands[] = $this->textCmd(46, $y - 30, 8, 'F1', $this->truncate($storeAddress, 52), [0.35, 0.33, 0.33]);
        $commands[] = $this->textCmd(46, $y - 42, 8, 'F1', 'Contact: ' . $this->truncate($storeContact ?: 'N/A', 40), [0.35, 0.33, 0.33]);

        $commands[] = $this->textCmd(324, $y - 2, 8, 'F2', 'BILLED & SHIPPED TO', [0.5, 0.45, 0.45]);
        $commands[] = $this->textCmd(324, $y - 16, 10, 'F2', $this->truncate($buyerName, 42), [0.15, 0.14, 0.14]);
        $commands[] = $this->textCmd(324, $y - 30, 8, 'F1', $this->truncate($buyerAddress, 52), [0.35, 0.33, 0.33]);
        $commands[] = $this->textCmd(324, $y - 42, 8, 'F1', 'Phone: ' . $this->truncate($buyerPhone ?: 'N/A', 40), [0.35, 0.33, 0.33]);

        $y -= 78;
        $commands[] = '0.22 0.20 0.20 rg';
        $commands[] = "36 " . ($y - 6) . " 540 22 re f";
        $commands[] = $this->textCmd(44, $y + 2, 8.5, 'F2', 'ITEM DESCRIPTION', [1, 1, 1]);
        $commands[] = $this->textCmd(285, $y + 2, 8.5, 'F2', 'VARIANT', [1, 1, 1]);
        $commands[] = $this->textCmd(395, $y + 2, 8.5, 'F2', 'QTY', [1, 1, 1]);
        $commands[] = $this->textCmd(440, $y + 2, 8.5, 'F2', 'UNIT PRICE', [1, 1, 1]);
        $commands[] = $this->textCmd(512, $y + 2, 8.5, 'F2', 'LINE TOTAL', [1, 1, 1]);

        $y -= 24;
        foreach ($sellerOrder->items as $idx => $item) {
            if ($y < 190) {
                break;
            }
            if ($idx % 2 === 1) {
                $commands[] = '0.98 0.97 0.97 rg';
                $commands[] = "36 " . ($y - 6) . " 540 20 re f";
            }
            $variantStr = implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) ?: 'Standard';
            $commands[] = $this->textCmd(44, $y, 8.5, 'F1', $this->truncate((string) $item->product_name, 42), [0.15, 0.14, 0.14]);
            $commands[] = $this->textCmd(285, $y, 8, 'F1', $this->truncate($variantStr, 20), [0.35, 0.33, 0.33]);
            $commands[] = $this->textCmd(400, $y, 8.5, 'F2', (string) ((int) $item->quantity), [0.15, 0.14, 0.14]);
            $commands[] = $this->textCmd(440, $y, 8.5, 'F1', 'PHP ' . number_format((float) $item->discounted_unit_price, 2), [0.15, 0.14, 0.14]);
            $commands[] = $this->textCmd(512, $y, 8.5, 'F2', 'PHP ' . number_format((float) $item->line_total, 2), [0.15, 0.14, 0.14]);

            $commands[] = '0.88 0.86 0.86 RG 0.5 w';
            $commands[] = "36 " . ($y - 6) . " m 576 " . ($y - 6) . " l S";
            $y -= 20;
        }

        $y -= 10;
        $leftY = $y;
        $logisticsName = $sellerOrder->logisticsProfile?->business_name ?? 'Standard Partner Logistics';
        $trackingNo = $sellerOrder->shipment?->tracking_number ?? 'N/A';
        $paymentRef = $sellerOrder->payment?->payment_reference ?? 'N/A';

        $commands[] = $this->textCmd(44, $leftY, 8.5, 'F2', 'LOGISTICS & SETTLEMENT DETAILS', [0.365, 0.192, 0.251]);
        $commands[] = $this->textCmd(44, $leftY - 14, 8, 'F1', 'Logistics Partner: ' . $this->truncate($logisticsName, 40), [0.35, 0.33, 0.33]);
        $commands[] = $this->textCmd(44, $leftY - 26, 8, 'F1', 'Tracking Number: ' . $trackingNo, [0.35, 0.33, 0.33]);
        $commands[] = $this->textCmd(44, $leftY - 38, 8, 'F1', 'Payment Reference: ' . $paymentRef, [0.35, 0.33, 0.33]);
        $commands[] = $this->textCmd(44, $leftY - 50, 8, 'F1', 'Order Status: ' . $sellerOrder->status_label, [0.35, 0.33, 0.33]);

        $summaryRows = [
            ['Merchandise Subtotal:', 'PHP ' . number_format($originalSub, 2), false],
        ];
        if ($storeDiscount > 0) {
            $summaryRows[] = ['Store / Bundle Discount:', '-PHP ' . number_format($storeDiscount, 2), false];
        }
        if ($platformDiscount > 0) {
            $summaryRows[] = ['Platform Voucher Discount:', '-PHP ' . number_format($platformDiscount, 2), false];
        }
        if ($coinsDiscount > 0) {
            $summaryRows[] = ["Storkia Coins ({$coinsUsed} Coins):", '-PHP ' . number_format($coinsDiscount, 2), false];
        }
        $summaryRows[] = ['Net Product Subtotal:', 'PHP ' . number_format($netSub, 2), false];
        $summaryRows[] = ['Shipping Fee (' . number_format((float) $sellerOrder->total_weight, 2) . ' kg):', 'PHP ' . number_format($originalShip, 2), false];
        if ($shippingDiscount > 0) {
            $summaryRows[] = ['Shipping Voucher Discount:', '-PHP ' . number_format($shippingDiscount, 2), false];
        }
        $summaryRows[] = ['Net Shipping Fee:', 'PHP ' . number_format($netShip, 2), false];
        $summaryRows[] = ['TOTAL AMOUNT (' . $payMethod . '):', 'PHP ' . number_format($grandTotal, 2), true];

        $rightY = $y;
        foreach ($summaryRows as [$label, $val, $isBold]) {
            if ($isBold) {
                $commands[] = '0.965 0.847 0.741 rg';
                $commands[] = "330 " . ($rightY - 5) . " 246 20 re f";
                $commands[] = $this->textCmd(338, $rightY + 1, 9.5, 'F2', $label, [0.365, 0.192, 0.251]);
                $commands[] = $this->textCmd(488, $rightY + 1, 10, 'F2', $val, [0.812, 0.255, 0.451]);
            } else {
                $commands[] = $this->textCmd(338, $rightY, 8.5, 'F1', $label, [0.35, 0.33, 0.33]);
                $commands[] = $this->textCmd(492, $rightY, 8.5, 'F2', $val, [0.15, 0.14, 0.14]);
            }
            $rightY -= 16;
        }

        $commands[] = $this->textCmd(44, 48, 7.5, 'F1', 'System-generated Official E-Invoice from Storkia Marketplace. Valid as official proof of purchase and warranty reference.', [0.55, 0.52, 0.52]);

        $stream = implode("\n", $commands);

        return $this->compileSinglePagePdf($stream);
    }

    protected function textCmd(float $x, float $y, float $size, string $font, string $text, array $rgb = [0, 0, 0]): string
    {
        $r = number_format((float) ($rgb[0] ?? 0), 3, '.', '');
        $g = number_format((float) ($rgb[1] ?? 0), 3, '.', '');
        $b = number_format((float) ($rgb[2] ?? 0), 3, '.', '');
        $safe = $this->escapePdfText($text);
        $xStr = number_format($x, 2, '.', '');
        $yStr = number_format($y, 2, '.', '');
        $sStr = number_format($size, 2, '.', '');

        return "BT /{$font} {$sStr} Tf {$r} {$g} {$b} rg {$xStr} {$yStr} Td ({$safe}) Tj ET";
    }

    protected function escapePdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '', $text);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $ascii);
    }

    protected function truncate(string $text, int $max): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) <= $max) {
            return $clean;
        }
        return mb_substr($clean, 0, max(1, $max - 3)) . '...';
    }

    protected function compileSinglePagePdf(string $contentStream): string
    {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $objects[6] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}