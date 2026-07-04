<?php

declare(strict_types=1);

namespace App\Services;

final class ConnectionTester
{
    public function test(array $credentials): array
    {
        $type = (string) ($credentials['type'] ?? '');
        if ($type === 'email_imap_smtp') {
            return $this->testEmail($credentials);
        }

        if ($type === 'obraok_api') {
            return $this->testApi($credentials);
        }

        return ['ok' => false, 'message' => 'Tipo de conector no soportado para prueba automatica.'];
    }

    private function testEmail(array $credentials): array
    {
        $checks = [];
        foreach ([
            'IMAP' => [(string) ($credentials['imap_host'] ?? ''), (int) ($credentials['imap_port'] ?? 993)],
            'SMTP' => [(string) ($credentials['smtp_host'] ?? ''), (int) ($credentials['smtp_port'] ?? 587)],
        ] as $label => [$host, $port]) {
            if ($host === '' || $port <= 0) {
                return ['ok' => false, 'message' => "{$label}: host o puerto incompleto."];
            }

            $result = $this->canOpenSocket($host, $port, 8);
            $checks[] = "{$label}: " . ($result['ok'] ? 'alcanzable' : $result['message']);
            if (!$result['ok']) {
                return ['ok' => false, 'message' => implode(' / ', $checks)];
            }
        }

        return ['ok' => true, 'message' => implode(' / ', $checks)];
    }

    private function testApi(array $credentials): array
    {
        $baseUrl = trim((string) ($credentials['api_base_url'] ?? ''));
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'URL API invalida.'];
        }

        $parts = parse_url($baseUrl);
        $host = (string) ($parts['host'] ?? '');
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $result = $this->canOpenSocket($host, $port, 8);

        return $result['ok']
            ? ['ok' => true, 'message' => 'API alcanzable. Token guardado para llamadas autenticadas futuras.']
            : ['ok' => false, 'message' => 'API no alcanzable: ' . $result['message']];
    }

    private function canOpenSocket(string $host, int $port, int $timeout): array
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);
        if (is_resource($socket)) {
            fclose($socket);
            return ['ok' => true, 'message' => 'conexion abierta'];
        }

        return ['ok' => false, 'message' => trim($errorMessage) !== '' ? $errorMessage : 'sin respuesta'];
    }
}
