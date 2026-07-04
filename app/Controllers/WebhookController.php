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
}
