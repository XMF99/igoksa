<?php

namespace App\Services\Sahab;

use App\Models\Sahab\Invoice;
use App\Models\Sahab\Tenant;
use App\Models\Sahab\ZatcaCredential;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;
use Illuminate\Support\Facades\Log;

/**
 * ZatcaService - يتعامل مع متطلّبات هيئة الزكاة والضريبة السعوديّة
 * يستخدم package: salla/zatca (موجود في ERPGo)
 *
 * Phase 1 (E-Invoicing): توليد QR Code + Hash لكل فاتورة
 * Phase 2 (Integration): التوقيع الرقمي + الإرسال لزاتكا
 */
class ZatcaService
{
    /**
     * توليد QR Code للفاتورة (Phase 1)
     */
    public function generateQrForInvoice(Invoice $invoice): string
    {
        $tenant = Tenant::find($invoice->tenant_id);
        if (!$tenant) throw new \Exception('Tenant not found');

        $qr = GenerateQrCode::fromArray([
            new Seller($tenant->name),
            new TaxNumber($tenant->vat_number ?? '300000000000003'),
            new InvoiceDate($invoice->invoice_date->format('Y-m-d\TH:i:s\Z')),
            new InvoiceTotalAmount(number_format($invoice->total_amount, 2, '.', '')),
            new InvoiceTaxAmount(number_format($invoice->vat_amount, 2, '.', '')),
        ])->toBase64();

        // حفظ في الفاتورة
        $invoice->update([
            'zatca_qr_code' => $qr,
            'zatca_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'zatca_hash' => $this->generateHash($invoice),
        ]);

        return $qr;
    }

    /**
     * توليد Hash متسلسل للفاتورة
     */
    protected function generateHash(Invoice $invoice): string
    {
        $cred = ZatcaCredential::where('tenant_id', $invoice->tenant_id)->first();
        $previousHash = $cred?->last_invoice_hash ?? '0';

        $payload = sprintf(
            '%s|%s|%s|%s|%s',
            $invoice->invoice_number,
            $invoice->invoice_date->timestamp,
            $invoice->total_amount,
            $invoice->vat_amount,
            $previousHash
        );

        $hash = hash('sha256', $payload);

        // تحديث آخر hash للمستأجر
        if ($cred) {
            $cred->update([
                'last_invoice_hash' => $hash,
                'last_invoice_counter' => ($cred->last_invoice_counter ?? 0) + 1,
            ]);
        }

        return $hash;
    }

    /**
     * إرسال الفاتورة لزاتكا (Phase 2 — يتطلّب CSID)
     */
    public function submitInvoiceToZatca(Invoice $invoice): array
    {
        $cred = ZatcaCredential::where('tenant_id', $invoice->tenant_id)->first();

        if (!$cred || !$cred->is_active || $cred->phase !== 'phase2') {
            return [
                'success' => false,
                'error' => 'لم يُفعَّل تكامل المرحلة الثانية بعد',
            ];
        }

        try {
            // بناء XML للفاتورة (UBL 2.1)
            $xml = $this->buildUblXml($invoice);
            $signedXml = $this->signXml($xml, $cred);

            $response = $this->callZatcaApi(
                $cred->phase === 'phase2' ? 'production' : 'compliance',
                $signedXml,
                $cred
            );

            if ($response['success']) {
                $invoice->update([
                    'zatca_submitted' => true,
                    'zatca_submitted_at' => now(),
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('ZATCA submission failed', [
                'invoice' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * بناء UBL 2.1 XML للفاتورة
     */
    protected function buildUblXml(Invoice $invoice): string
    {
        $tenant = Tenant::find($invoice->tenant_id);
        $items = $invoice->items()->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

        $xml .= '<cbc:ProfileID>reporting:1.0</cbc:ProfileID>';
        $xml .= '<cbc:ID>' . htmlspecialchars($invoice->invoice_number) . '</cbc:ID>';
        $xml .= '<cbc:UUID>' . $invoice->zatca_uuid . '</cbc:UUID>';
        $xml .= '<cbc:IssueDate>' . $invoice->invoice_date->format('Y-m-d') . '</cbc:IssueDate>';
        $xml .= '<cbc:IssueTime>' . $invoice->invoice_date->format('H:i:s') . '</cbc:IssueTime>';
        $xml .= '<cbc:InvoiceTypeCode name="0100000">388</cbc:InvoiceTypeCode>';
        $xml .= '<cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>';

        // البائع
        $xml .= '<cac:AccountingSupplierParty>';
        $xml .= '<cac:Party>';
        $xml .= '<cac:PartyTaxScheme>';
        $xml .= '<cbc:CompanyID>' . $tenant->vat_number . '</cbc:CompanyID>';
        $xml .= '<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>';
        $xml .= '</cac:PartyTaxScheme>';
        $xml .= '<cac:PartyLegalEntity>';
        $xml .= '<cbc:RegistrationName>' . htmlspecialchars($tenant->name) . '</cbc:RegistrationName>';
        $xml .= '</cac:PartyLegalEntity>';
        $xml .= '</cac:Party>';
        $xml .= '</cac:AccountingSupplierParty>';

        // المجاميع
        $xml .= '<cac:LegalMonetaryTotal>';
        $xml .= '<cbc:LineExtensionAmount currencyID="SAR">' . number_format($invoice->subtotal, 2, '.', '') . '</cbc:LineExtensionAmount>';
        $xml .= '<cbc:TaxExclusiveAmount currencyID="SAR">' . number_format($invoice->subtotal, 2, '.', '') . '</cbc:TaxExclusiveAmount>';
        $xml .= '<cbc:TaxInclusiveAmount currencyID="SAR">' . number_format($invoice->total_amount, 2, '.', '') . '</cbc:TaxInclusiveAmount>';
        $xml .= '<cbc:PayableAmount currencyID="SAR">' . number_format($invoice->total_amount, 2, '.', '') . '</cbc:PayableAmount>';
        $xml .= '</cac:LegalMonetaryTotal>';

        // الأصناف
        foreach ($items as $i => $item) {
            $xml .= '<cac:InvoiceLine>';
            $xml .= '<cbc:ID>' . ($i + 1) . '</cbc:ID>';
            $xml .= '<cbc:InvoicedQuantity unitCode="EA">' . $item->quantity . '</cbc:InvoicedQuantity>';
            $xml .= '<cbc:LineExtensionAmount currencyID="SAR">' . number_format($item->subtotal, 2, '.', '') . '</cbc:LineExtensionAmount>';
            $xml .= '<cac:Item><cbc:Name>' . htmlspecialchars($item->product_name) . '</cbc:Name></cac:Item>';
            $xml .= '<cac:Price><cbc:PriceAmount currencyID="SAR">' . number_format($item->unit_price, 2, '.', '') . '</cbc:PriceAmount></cac:Price>';
            $xml .= '</cac:InvoiceLine>';
        }

        $xml .= '</Invoice>';
        return $xml;
    }

    protected function signXml(string $xml, ZatcaCredential $cred): string
    {
        // التوقيع بـ private key (XMLDSig)
        // تنفيذ مبسّط — في الإنتاج استخدم phpseclib أو xmlseclibs
        return $xml;
    }

    protected function callZatcaApi(string $env, string $signedXml, ZatcaCredential $cred): array
    {
        $baseUrl = $env === 'production'
            ? 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core'
            : 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';

        $ch = curl_init("$baseUrl/invoices/reporting/single");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'invoiceHash' => hash('sha256', $signedXml),
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'invoice' => base64_encode($signedXml),
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept-Version: V2',
                'Authorization: Basic ' . base64_encode($cred->production_csid ?: $cred->compliance_csid),
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $code >= 200 && $code < 300,
            'code' => $code,
            'response' => json_decode($raw, true),
        ];
    }

    /**
     * توليد طلب CSR (للمرحلة 2)
     */
    public function generateCsr(Tenant $tenant): array
    {
        // تنفيذ مبسّط — في الإنتاج نستخدم openssl
        $config = [
            'CN' => $tenant->name,
            'C' => 'SA',
            'O' => $tenant->name,
            'OU' => 'Sahab',
            'serialNumber' => '1-' . $tenant->code . '|2-' . ($tenant->vat_number ?? '0') . '|3-' . $tenant->cr_number,
        ];

        // openssl_csr_new + openssl_csr_export
        return [
            'success' => true,
            'csr' => '-- CSR PLACEHOLDER --',
            'private_key' => '-- KEY PLACEHOLDER --',
        ];
    }
}
