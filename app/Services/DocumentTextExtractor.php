<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

final class DocumentTextExtractor
{
    public function extract(string $path, string $originalName, ?string $mimeType = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $text = match ($extension) {
            'txt' => $this->plainText($path),
            'csv' => $this->csvText($path),
            'docx' => $this->docxText($path),
            'xlsx' => $this->xlsxText($path),
            'pdf' => $this->pdfText($path),
            default => '',
        };

        $text = $this->normalize($text);

        return [
            'text' => $text,
            'characters' => strlen($text),
            'status' => $text !== '' ? 'processed' : 'failed',
            'summary' => $this->summary($text, $extension, $mimeType),
        ];
    }

    private function plainText(string $path): string
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function csvText(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            return '';
        }

        $rows = [];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false && $count < 1000) {
            $rows[] = implode(' | ', array_map('trim', $row));
            $count++;
        }
        fclose($handle);

        return implode("\n", $rows);
    }

    private function docxText(string $path): string
    {
        $xml = $this->zipEntry($path, 'word/document.xml');
        if ($xml === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml))) ?? '';
    }

    private function xlsxText(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $sharedStrings = $this->sharedStrings($zip);
        $rows = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_starts_with($name, 'xl/worksheets/sheet') || !str_ends_with($name, '.xml')) {
                continue;
            }

            $xml = (string) $zip->getFromName($name);
            preg_match_all('/<c([^>]*)>(.*?)<\/c>/s', $xml, $cells, PREG_SET_ORDER);
            $values = [];
            foreach ($cells as $cell) {
                $attributes = $cell[1] ?? '';
                $body = $cell[2] ?? '';
                preg_match('/<v>(.*?)<\/v>/s', $body, $match);
                $value = html_entity_decode(strip_tags($match[1] ?? ''), ENT_QUOTES, 'UTF-8');
                if (str_contains($attributes, 't="s"')) {
                    $value = $sharedStrings[(int) $value] ?? $value;
                }
                if ($value !== '') {
                    $values[] = $value;
                }
            }
            if ($values) {
                $rows[] = basename($name) . ': ' . implode(' | ', array_slice($values, 0, 400));
            }
        }

        $zip->close();
        return implode("\n", $rows);
    }

    private function pdfText(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $raw = (string) file_get_contents($path);
        preg_match_all('/\(([^()]{3,})\)/', $raw, $matches);
        $parts = array_map(fn (string $part): string => str_replace(['\\(', '\\)', '\\n', '\\r'], ['(', ')', "\n", "\r"], $part), $matches[1] ?? []);

        return implode("\n", array_slice($parts, 0, 2000));
    }

    private function zipEntry(string $path, string $entry): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $content = (string) $zip->getFromName($entry);
        $zip->close();

        return $content;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = (string) $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === '') {
            return [];
        }

        preg_match_all('/<si>(.*?)<\/si>/s', $xml, $items);
        return array_map(
            fn (string $item): string => trim(html_entity_decode(strip_tags($item), ENT_QUOTES, 'UTF-8')),
            $items[1] ?? []
        );
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function summary(string $text, string $extension, ?string $mimeType): string
    {
        if ($text === '') {
            return 'Archivo guardado, pero no se pudo extraer texto con el extractor inicial. Formato: ' . strtoupper($extension ?: ($mimeType ?? 'desconocido')) . '.';
        }

        $preview = strlen($text) > 180 ? substr($text, 0, 177) . '...' : $text;
        return 'Texto extraido e indexado para memoria empresarial. Vista previa: ' . $preview;
    }
}
