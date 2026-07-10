<?php

declare(strict_types=1);

namespace App\Services;

final class EmailBodyFormatter
{
    public static function formatEmailBody(string $body): array
    {
        try {
            $parts = self::splitQuotedText($body);
            $main = trim($parts['main']);
            $quoted = trim($parts['quoted']);
            $signature = trim($parts['signature']);
            $isHtml = self::looksLikeHtml($main);

            return [
                'body_html' => $isHtml ? self::sanitizeEmailHtml($main) : self::formatPlainText($main),
                'quoted_html' => $quoted !== '' ? self::formatPlainText($quoted) : '',
                'signature_html' => $signature !== '' ? self::formatPlainText($signature) : '',
                'has_quoted' => $quoted !== '',
                'has_signature' => $signature !== '',
                'is_html' => $isHtml,
            ];
        } catch (\Throwable) {
            return [
                'body_html' => self::formatPlainText($body),
                'quoted_html' => '',
                'signature_html' => '',
                'has_quoted' => false,
                'has_signature' => false,
                'is_html' => false,
            ];
        }
    }

    public static function splitQuotedText(string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $main = [];
        $quoted = [];
        $signature = [];
        $inSignature = false;

        foreach (explode("\n", $body) as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '>')) {
                $quoted[] = preg_replace('/^>\s?/', '', $trimmed) ?? $trimmed;
                continue;
            }

            if (trim($line) === '--' || trim($line) === '-- ') {
                $inSignature = true;
                continue;
            }

            if ($inSignature) {
                $signature[] = $line;
                continue;
            }

            $main[] = $line;
        }

        return [
            'main' => trim(implode("\n", $main)),
            'quoted' => trim(implode("\n", $quoted)),
            'signature' => trim(implode("\n", $signature)),
        ];
    }

    public static function sanitizeEmailHtml(string $html): string
    {
        $html = preg_replace('/<\s*(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? '';
        $html = preg_replace('/<\s*(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*\/?>/is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '$1="#"', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*javascript:[^\s>]*/i', '$1="#"', $html) ?? '';
        $html = strip_tags($html, '<p><br><div><span><strong><b><em><i><u><ul><ol><li><blockquote><a><table><thead><tbody><tr><td><th>');
        $html = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $matches): string {
            $attrs = $matches[1] ?? '';
            if (!preg_match('/\bhref\s*=/i', $attrs)) {
                return '<a>';
            }

            return '<a' . $attrs . ' target="_blank" rel="noopener noreferrer">';
        }, $html) ?? $html;

        return trim($html);
    }

    private static function formatPlainText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '<p class="email-empty">Sin contenido.</p>';
        }

        $paragraphs = [];
        foreach (preg_split("/\n{2,}/", $text) ?: [$text] as $block) {
            $safe = htmlspecialchars(trim($block), ENT_QUOTES, 'UTF-8');
            $safe = self::linkify($safe);
            $paragraphs[] = '<p>' . nl2br($safe, false) . '</p>';
        }

        return implode('', $paragraphs);
    }

    private static function linkify(string $safeText): string
    {
        return preg_replace_callback(
            '~https?://[^\s<]+~i',
            static fn (array $matches): string => '<a href="' . $matches[0] . '" target="_blank" rel="noopener noreferrer">' . $matches[0] . '</a>',
            $safeText
        ) ?? $safeText;
    }

    private static function looksLikeHtml(string $body): bool
    {
        return $body !== strip_tags($body) && preg_match('/<\/?[a-z][\s\S]*>/i', $body) === 1;
    }
}
