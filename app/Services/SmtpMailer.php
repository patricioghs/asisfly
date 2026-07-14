<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpMailer
{
    /** @param array{smtp_host?:string,smtp_port?:int,smtp_encryption?:string,username?:string,password?:string,email_address?:string} $credentials */
    public function send(array $credentials, string $to, string $subject, string $body): void
    {
        $host = trim((string) ($credentials['smtp_host'] ?? ''));
        $port = (int) ($credentials['smtp_port'] ?? 587);
        $encryption = $this->encryptionForPort((int) ($credentials['smtp_port'] ?? 587), (string) ($credentials['smtp_encryption'] ?? 'tls'));
        $username = trim((string) ($credentials['username'] ?? $credentials['email_address'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');
        $from = trim((string) ($credentials['email_address'] ?? $username));

        if ($host === '' || $port <= 0 || $username === '' || $password === '' || $from === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Credenciales SMTP incompletas o destinatario invalido.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @fsockopen($remote, $port, $errno, $errstr, 20);
        if (!$socket) {
            throw new RuntimeException('No se pudo conectar a SMTP: ' . ($errstr ?: 'sin detalle'));
        }

        try {
            stream_set_timeout($socket, 20);
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO asisfly.local', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
                }
                $this->command($socket, 'EHLO asisfly.local', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $this->write($socket, $this->message($from, $to, $subject, $body) . "\r\n.");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $command, array $expected): string
    {
        $this->write($socket, $command);
        return $this->expect($socket, $expected);
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    /** @param resource $socket */
    private function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }

        if ($response === '') {
            $meta = stream_get_meta_data($socket);
            $reason = !empty($meta['timed_out'])
                ? 'el servidor SMTP no respondio a tiempo'
                : (!empty($meta['eof']) ? 'el servidor SMTP cerro la conexion' : 'no hubo respuesta del servidor SMTP');
            throw new RuntimeException($reason . '. Verifica que el puerto y cifrado coincidan (587 TLS, 465 SSL o 25 sin cifrado).');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP respondio inesperadamente: ' . trim($response));
        }

        return $response;
    }

    private function encryptionForPort(int $port, string $configured): string
    {
        return match ($port) {
            465 => 'ssl',
            587 => 'tls',
            25 => 'none',
            default => in_array(strtolower($configured), ['ssl', 'tls', 'none'], true) ? strtolower($configured) : 'tls',
        };
    }

    private function message(string $from, string $to, string $subject, string $body): string
    {
        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date(DATE_RFC2822),
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
    }
}
