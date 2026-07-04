<?php

declare(strict_types=1);

namespace App\Services;

final class QuotePdfGenerator
{
    public function generate(array $quote, array $items, array $company): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/quotes/company_' . (int) $quote['company_id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $quote['quote_number']) . '.pdf';
        $path = $dir . '/' . $fileName;
        $lines = $this->lines($quote, $items, $company);
        $content = $this->pdf($lines);
        file_put_contents($path, $content);

        return 'quotes/company_' . (int) $quote['company_id'] . '/' . $fileName;
    }

    private function lines(array $quote, array $items, array $company): array
    {
        $lines = [
            'AsisFly',
            'Cotizacion ' . $quote['quote_number'],
            'Empresa: ' . ($company['name'] ?? 'AsisFly'),
            'Cliente: ' . ($quote['client'] ?? $quote['customer_name'] ?? 'Cliente'),
            'Fecha: ' . date('Y-m-d', strtotime((string) ($quote['created_at'] ?? 'now'))),
            'Valida hasta: ' . ($quote['valid_until'] ?? 'Sin fecha'),
            '',
            'Detalle',
        ];

        foreach ($items as $item) {
            $lines[] = '- ' . $item['description'] . ' | Cant: ' . $item['quantity'] . ' | Unit: ' . $this->money((float) $item['unit_price'], $quote['currency']) . ' | Total: ' . $this->money((float) $item['total'], $quote['currency']);
        }

        $lines[] = '';
        $lines[] = 'Subtotal: ' . $this->money((float) $quote['subtotal'], $quote['currency']);
        $lines[] = 'Descuento: ' . $this->money((float) $quote['discount'], $quote['currency']);
        $lines[] = 'Impuestos: ' . $this->money((float) $quote['tax'], $quote['currency']);
        $lines[] = 'TOTAL: ' . $this->money((float) $quote['total'], $quote['currency']);
        $lines[] = '';
        $lines[] = 'Notas: ' . ($quote['notes'] ?? 'Gracias por considerar AsisFly.');
        $lines[] = 'Terminos: ' . ($quote['terms'] ?? 'Cotizacion sujeta a aprobacion comercial.');

        return $lines;
    }

    private function pdf(array $lines): string
    {
        $objects = [];
        $content = "BT\n/F1 12 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            $safe = $this->escape($line);
            if ($index > 0) {
                $content .= "0 -18 Td\n";
            }
            $content .= "({$safe}) Tj\n";
        }
        $content .= "ET";

        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = '5 0 obj << /Length ' . strlen($content) . " >> stream\n{$content}\nendstream endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        $value = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function money(float $amount, string $currency): string
    {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }
}
