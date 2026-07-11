<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AiProviderRepository;

final class WhatsAppEmbeddedSignup
{
    public function settings(): array
    {
        $repo = new AiProviderRepository();

        return [
            'app_id' => $repo->resolvedPlatformApiKey('whatsapp_meta_app_id'),
            'config_id' => $repo->resolvedPlatformApiKey('whatsapp_meta_config_id'),
            'app_secret' => $repo->resolvedPlatformApiKey('whatsapp_meta_app_secret'),
            'graph_version' => 'v20.0',
        ];
    }

    public function status(): array
    {
        $settings = $this->settings();
        $missing = [];
        if (trim((string) $settings['app_id']) === '') {
            $missing[] = 'Meta App ID';
        }

        if (trim((string) $settings['config_id']) === '') {
            $missing[] = 'Configuration ID';
        }

        return [
            'configured' => empty($missing),
            'missing' => $missing,
            'settings' => [
                'app_id' => $settings['app_id'] !== '' ? '****' . substr((string) $settings['app_id'], -4) : '',
                'config_id' => $settings['config_id'] !== '' ? '****' . substr((string) $settings['config_id'], -4) : '',
                'app_secret' => $settings['app_secret'] !== '' ? 'Configurado' : 'Opcional',
            ],
        ];
    }

    public function publicConfig(): array
    {
        $settings = $this->settings();

        return [
            'configured' => trim((string) $settings['app_id']) !== '' && trim((string) $settings['config_id']) !== '',
            'app_id' => (string) $settings['app_id'],
            'config_id' => (string) $settings['config_id'],
            'graph_version' => (string) $settings['graph_version'],
            'redirect_uri' => $this->absoluteUrl('/integrations/whatsapp/callback'),
            'webhook_url' => $this->absoluteUrl('/webhooks/whatsapp-cloud'),
        ];
    }

    private function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return $host !== '' ? $scheme . '://' . $host . \url($path) : \url($path);
    }
}
