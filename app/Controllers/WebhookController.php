<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OmnichannelRepository;

final class WebhookController
{
    public function omnichannel(): void
    {
        header('Content-Type: application/json');

        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $result = (new OmnichannelRepository())->receiveWebhook($token, $payload);
        http_response_code($result['ok'] ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function whatsappCloudVerify(): void
    {
        $mode = trim((string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? ''));
        $token = trim((string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? ''));
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

        if ($mode === 'subscribe' && $challenge !== '' && (new OmnichannelRepository())->verifyWebhookToken($token)) {
            header('Content-Type: text/plain');
            echo $challenge;
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Token de verificacion WhatsApp invalido.'], JSON_UNESCAPED_UNICODE);
    }

    public function whatsappCloudReceive(): void
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $result = (new OmnichannelRepository())->receiveWhatsAppCloud($payload);
        http_response_code($result['ok'] ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
