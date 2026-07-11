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

        if ($type === 'whatsapp_cloud') {
            return $this->testWhatsAppCloud($credentials);
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

    private function testWhatsAppCloud(array $credentials): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));
        $version = $this->graphVersion((string) ($credentials['graph_version'] ?? 'v20.0'));

        if ($token === '' || $phoneNumberId === '') {
            return ['ok' => false, 'message' => 'Faltan access token o Phone Number ID.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'La extension cURL de PHP no esta habilitada.'];
        }

        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '?fields=display_phone_number,verified_name';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 12,
        ]);

        $response = (string) curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $name = (string) ($decoded['verified_name'] ?? $decoded['display_phone_number'] ?? $phoneNumberId);
            return ['ok' => true, 'message' => 'WhatsApp Cloud conectado: ' . $name . '.'];
        }

        $detail = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $response) : ($error !== '' ? $error : $response);
        return ['ok' => false, 'message' => 'Meta no valido la cuenta: ' . trim($detail)];
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

    private function graphVersion(string $version): string
    {
        $version = trim($version);
        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v20.0';
    }
}
