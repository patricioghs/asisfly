<?php

declare(strict_types=1);

namespace App\Services;

final class WhatsAppCloudClient
{
    /** @param array{access_token?:string,phone_number_id?:string,graph_version?:string} $credentials */
    public function sendText(array $credentials, string $to, string $body): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));
        $version = $this->graphVersion((string) ($credentials['graph_version'] ?? 'v20.0'));
        $to = preg_replace('/\D+/', '', $to) ?: '';
        $body = trim($body);

        if ($token === '' || $phoneNumberId === '') {
            return ['ok' => false, 'message' => 'Faltan access token o Phone Number ID de WhatsApp Cloud.'];
        }

        if ($to === '') {
            return ['ok' => false, 'message' => 'El cliente no tiene numero WhatsApp valido.'];
        }

        if ($body === '') {
            return ['ok' => false, 'message' => 'La respuesta WhatsApp esta vacia.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'La extension cURL de PHP no esta habilitada.'];
        }

        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = (string) curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $messageId = (string) ($decoded['messages'][0]['id'] ?? '');
            return [
                'ok' => true,
                'message' => 'WhatsApp enviado a ' . $to . ($messageId !== '' ? ' (' . $messageId . ')' : '') . '.',
                'provider_message_id' => $messageId,
                'response' => is_array($decoded) ? $decoded : ['raw' => $response],
            ];
        }

        $detail = is_array($decoded)
            ? (string) ($decoded['error']['message'] ?? $response)
            : ($error !== '' ? $error : $response);

        return [
            'ok' => false,
            'message' => 'No se pudo enviar WhatsApp Cloud: ' . trim($detail),
            'http_code' => $httpCode,
        ];
    }

    private function graphVersion(string $version): string
    {
        $version = trim($version);
        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v20.0';
    }
}
