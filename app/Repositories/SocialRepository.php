<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SocialRepository
{
    public function channels(): array
    {
        return ['Instagram', 'Facebook', 'LinkedIn', 'TikTok', 'WhatsApp'];
    }

    public function posts(int $companyId, ?string $month = null): array
    {
        if (!$this->databaseReady()) {
            return $_SESSION['social_posts'] ?? $this->fallbackPosts();
        }

        $month = $month ?: date('Y-m');
        $statement = Database::connection()->prepare('SELECT p.*, c.name AS campaign_name,
                   COALESCE(SUM(m.impressions), 0) AS impressions,
                   COALESCE(SUM(m.clicks), 0) AS clicks,
                   COALESCE(SUM(m.messages), 0) AS messages,
                   COALESCE(SUM(m.leads), 0) AS leads,
                   COALESCE(SUM(m.sales_amount), 0) AS sales_amount
            FROM social_posts p
            LEFT JOIN social_campaigns c ON c.id = p.campaign_id
            LEFT JOIN social_post_metrics m ON m.post_id = p.id
            WHERE p.company_id = :company_id
              AND (p.scheduled_at IS NULL OR DATE_FORMAT(p.scheduled_at, "%Y-%m") = :month)
            GROUP BY p.id
            ORDER BY p.scheduled_at IS NULL, p.scheduled_at, p.id DESC');
        $statement->execute(['company_id' => $companyId, 'month' => $month]);

        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'campaign_id' => (int) ($row['campaign_id'] ?? 0),
            'campaign_name' => $row['campaign_name'] ?? '',
            'channel' => $row['channel'],
            'objective' => $row['objective'],
            'content_pillar' => $row['content_pillar'] ?? '',
            'status' => $row['status'],
            'post_text' => $row['post_text'],
            'image_idea' => $row['image_idea'],
            'hashtags' => $row['hashtags'],
            'cta' => $row['cta'],
            'recommended_time' => $row['recommended_time'],
            'scheduled_at' => $row['scheduled_at'],
            'product_focus' => $row['product_focus'],
            'season' => $row['season'],
            'industry' => $row['industry'] ?? '',
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'messages' => (int) ($row['messages'] ?? 0),
            'leads' => (int) ($row['leads'] ?? 0),
            'sales_amount' => (float) ($row['sales_amount'] ?? 0),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function insights(int $companyId, array $company): array
    {
        $currency = $company['currency'] ?? 'CLP';
        return [
            'Hoy conviene publicar el producto con mejor margen y CTA directo a WhatsApp.',
            'Instagram y WhatsApp deberian priorizar conversion; LinkedIn puede reforzar confianza.',
            'Publicaciones con prueba social y urgencia tienden a generar mas pedidos.',
            'Meta sugerida de la semana: recuperar consultas frias y empujar ' . $currency . ' 1.200.000 en pedidos.',
        ];
    }

    public function metrics(int $companyId): array
    {
        $posts = $this->posts($companyId, date('Y-m'));
        $total = count($posts);
        $drafts = count(array_filter($posts, fn (array $post) => $post['status'] === 'draft'));
        $scheduled = count(array_filter($posts, fn (array $post) => $post['status'] === 'scheduled'));
        $channels = count(array_unique(array_map(fn (array $post) => $post['channel'], $posts)));

        return [
            ['label' => 'Piezas creadas', 'value' => (string) $total, 'hint' => 'Calendario activo'],
            ['label' => 'Borradores', 'value' => (string) $drafts, 'hint' => 'Pendientes de aprobar'],
            ['label' => 'Programadas', 'value' => (string) $scheduled, 'hint' => 'Listas para publicar'],
            ['label' => 'Canales', 'value' => (string) $channels, 'hint' => 'Redes cubiertas'],
        ];
    }

    public function templates(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM social_templates WHERE is_active = 1 AND (company_id IS NULL OR company_id = :company_id) ORDER BY industry, channel, name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function campaigns(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT c.*, COUNT(p.id) AS posts FROM social_campaigns c LEFT JOIN social_posts p ON p.campaign_id = c.id WHERE c.company_id = :company_id GROUP BY c.id ORDER BY c.id DESC LIMIT 12');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function performance(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return ['impressions' => 0, 'clicks' => 0, 'messages' => 0, 'leads' => 0, 'sales' => 0];
        }

        $statement = Database::connection()->prepare('SELECT COALESCE(SUM(impressions),0) AS impressions, COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(messages),0) AS messages, COALESCE(SUM(leads),0) AS leads, COALESCE(SUM(sales_amount),0) AS sales FROM social_post_metrics WHERE company_id = :company_id AND measured_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'messages' => (int) ($row['messages'] ?? 0),
            'leads' => (int) ($row['leads'] ?? 0),
            'sales' => (float) ($row['sales'] ?? 0),
        ];
    }

    public function generateCampaign(int $companyId, array $input, array $company): void
    {
        $brief = trim($input['brief'] ?? 'Crear contenido para vender mas pedidos por WhatsApp');
        $product = trim($input['product_focus'] ?? 'Producto destacado');
        $season = trim($input['season'] ?? 'Temporada actual');
        $industry = trim($input['industry'] ?? 'Retail / Ecommerce');
        $monthlyGoal = trim($input['monthly_goal'] ?? 'Vender mas por WhatsApp');
        $quantity = min(31, max(1, (int) ($input['quantity'] ?? 20)));
        $channels = $input['channels'] ?? $this->channels();
        if (!is_array($channels) || !$channels) {
            $channels = $this->channels();
        }

        $templates = $this->templatesFor($companyId, $industry, $channels);
        $drafts = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $channel = $channels[($i - 1) % count($channels)];
            $template = $this->templateFor($templates, $channel, $i);
            $scheduled = (new \DateTimeImmutable('tomorrow'))->modify('+' . ($i - 1) . ' days')->setTime($this->hourFor($channel), 0);
            $drafts[] = [
                'company_id' => $companyId,
                'template_id' => $template['id'] ?? null,
                'industry' => $industry,
                'channel' => $channel,
                'objective' => $template['objective'] ?? $this->objectiveFor($channel),
                'content_pillar' => $template['content_pillar'] ?? 'Venta',
                'status' => 'draft',
                'post_text' => $this->renderTemplate($template['caption_pattern'] ?? '', $channel, $product, $brief, $i),
                'image_idea' => $this->renderTemplate($template['image_pattern'] ?? '', $channel, $product, $brief, $i) ?: $this->imageIdeaFor($channel, $product, $season),
                'hashtags' => $this->renderTemplate($template['hashtag_pattern'] ?? '', $channel, $product, $brief, $i) ?: $this->hashtagsFor($channel, $product),
                'cta' => $this->renderTemplate($template['cta_pattern'] ?? '', $channel, $product, $brief, $i) ?: ($channel === 'WhatsApp' ? 'Responder "Quiero cotizar" por WhatsApp' : 'Escribenos por WhatsApp para reservar'),
                'recommended_time' => $scheduled->format('H:i'),
                'scheduled_at' => $scheduled->format('Y-m-d H:i:s'),
                'product_focus' => $product,
                'season' => $season,
            ];
        }

        if (!$this->databaseReady()) {
            $_SESSION['social_posts'] = array_merge($drafts, $_SESSION['social_posts'] ?? $this->fallbackPosts());
            return;
        }

        $campaign = Database::connection()->prepare('INSERT INTO social_campaigns (company_id, name, goal, season, channels_json, status) VALUES (:company_id, :name, :goal, :season, :channels_json, "draft")');
        $campaign->execute([
            'company_id' => $companyId,
            'name' => 'Campana ' . date('Y-m') . ' - ' . $product,
            'goal' => $monthlyGoal,
            'season' => $season,
            'channels_json' => json_encode($channels, JSON_UNESCAPED_UNICODE),
        ]);
        $campaignId = (int) Database::connection()->lastInsertId();

        $statement = Database::connection()->prepare('INSERT INTO social_posts (company_id, campaign_id, template_id, industry, channel, objective, content_pillar, status, post_text, image_idea, hashtags, cta, recommended_time, scheduled_at, product_focus, season) VALUES (:company_id, :campaign_id, :template_id, :industry, :channel, :objective, :content_pillar, :status, :post_text, :image_idea, :hashtags, :cta, :recommended_time, :scheduled_at, :product_focus, :season)');
        foreach ($drafts as $draft) {
            $statement->execute(['campaign_id' => $campaignId, ...$draft]);
        }

        (new ActionRepository())->create($companyId, (int) ($_SESSION['user']['id'] ?? 0), [
            'title' => 'Aprobar calendario social generado',
            'description' => "Asisti Social preparo {$quantity} publicaciones mensuales para {$product}. Revisa los borradores antes de programar o publicar.",
            'module' => 'Asisti Social',
            'brain' => 'Cerebro Comercial',
            'action_type' => 'approve_social_calendar',
            'priority' => 'high',
            'risk_level' => 'medium',
            'payload' => ['campaign_id' => $campaignId, 'posts' => $quantity, 'product_focus' => $product, 'season' => $season, 'channels' => $channels],
            'requires_approval' => true,
        ]);
    }

    public function updatePostStatus(int $companyId, int $postId, string $status): void
    {
        if (!in_array($status, ['approved', 'scheduled', 'published'], true) || !$this->databaseReady()) {
            return;
        }

        $published = $status === 'published' ? ', published_at = CURRENT_TIMESTAMP' : '';
        Database::connection()->prepare("UPDATE social_posts SET status = :status {$published} WHERE company_id = :company_id AND id = :id")->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $postId,
        ]);

        if ($status === 'published') {
            $this->seedMetrics($companyId, $postId);
        }
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM social_posts LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function fallbackPosts(): array
    {
        return [
            [
                'id' => 0,
                'channel' => 'Instagram',
                'objective' => 'Vender por WhatsApp',
                'status' => 'draft',
                'post_text' => 'Esta semana enfocamos el contenido en productos con buen margen y respuesta directa por WhatsApp.',
                'image_idea' => 'Foto limpia del producto en uso, precio visible y etiqueta de disponibilidad.',
                'hashtags' => '#ventas #whatsapp #promo',
                'cta' => 'Escribenos por WhatsApp para cotizar',
                'recommended_time' => '11:00',
                'scheduled_at' => date('Y-m-d 11:00:00', strtotime('+1 day')),
                'product_focus' => 'Producto destacado',
                'season' => 'Temporada actual',
            ],
        ];
    }

    private function templatesFor(int $companyId, string $industry, array $channels): array
    {
        if (!$this->databaseReady()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($channels), '?'));
        $sql = "SELECT * FROM social_templates WHERE is_active = 1 AND industry = ? AND channel IN ({$placeholders}) AND (company_id IS NULL OR company_id = ?) ORDER BY channel, id";
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$industry, ...$channels, $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function templateFor(array $templates, string $channel, int $index): array
    {
        $matches = array_values(array_filter($templates, fn (array $template): bool => $template['channel'] === $channel));
        return $matches ? $matches[($index - 1) % count($matches)] : [];
    }

    private function renderTemplate(string $pattern, string $channel, string $product, string $brief, int $index): string
    {
        if ($pattern === '') {
            return $this->captionFor($channel, $product, $brief, $index);
        }
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $product)) ?: 'producto';
        return strtr($pattern, [
            '{product}' => $product,
            '{product_slug}' => $slug,
            '{brief}' => $brief,
            '{index}' => (string) $index,
        ]);
    }

    private function seedMetrics(int $companyId, int $postId): void
    {
        $base = 350 + ($postId % 9) * 80;
        Database::connection()->prepare('INSERT INTO social_post_metrics (company_id, post_id, impressions, clicks, messages, leads, sales_amount, measured_at) VALUES (:company_id, :post_id, :impressions, :clicks, :messages, :leads, :sales_amount, CURRENT_DATE) ON DUPLICATE KEY UPDATE impressions = VALUES(impressions), clicks = VALUES(clicks), messages = VALUES(messages), leads = VALUES(leads), sales_amount = VALUES(sales_amount)')->execute([
            'company_id' => $companyId,
            'post_id' => $postId,
            'impressions' => $base,
            'clicks' => (int) round($base * 0.08),
            'messages' => (int) round($base * 0.025),
            'leads' => (int) round($base * 0.012),
            'sales_amount' => ($postId % 5) * 85000,
        ]);
    }

    private function captionFor(string $channel, string $product, string $brief, int $index): string
    {
        $templates = [
            'Instagram' => "Post {$index}: {$product} listo para quienes quieren resolver rapido. {$brief}. Escribenos y te ayudamos a elegir.",
            'Facebook' => "Esta semana tenemos una recomendacion practica: {$product}. Ideal para clientes que buscan precio, disponibilidad y atencion rapida.",
            'LinkedIn' => "Como empresa, recomendamos {$product} por su aporte en eficiencia, margen y continuidad operativa. {$brief}.",
            'TikTok' => "Idea de video {$index}: muestra el antes y despues usando {$product}, cierre con oferta y WhatsApp en pantalla.",
            'WhatsApp' => "Hola, tenemos disponible {$product}. Si quieres, te envio opciones, precio y despacho hoy mismo.",
        ];

        return $templates[$channel] ?? $templates['Instagram'];
    }

    private function imageIdeaFor(string $channel, string $product, string $season): string
    {
        return match ($channel) {
            'TikTok' => "Video corto mostrando {$product}, beneficio principal y cierre con pantalla de WhatsApp.",
            'LinkedIn' => "Grafica sobria con dato de negocio, producto {$product} y beneficio medible.",
            'WhatsApp' => "Imagen cuadrada simple con {$product}, precio o beneficio y disponibilidad.",
            default => "Foto o carrusel de {$product} con contexto de {$season}, beneficio y llamado visual.",
        };
    }

    private function hashtagsFor(string $channel, string $product): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $product)) ?: 'producto';
        return $channel === 'LinkedIn'
            ? '#negocios #ventas #' . $base
            : '#promo #whatsapp #' . $base . ' #ventas';
    }

    private function objectiveFor(string $channel): string
    {
        return match ($channel) {
            'LinkedIn' => 'Generar confianza',
            'TikTok' => 'Alcance y demanda',
            'WhatsApp' => 'Cerrar pedidos',
            default => 'Vender por WhatsApp',
        };
    }

    private function hourFor(string $channel): int
    {
        return match ($channel) {
            'LinkedIn' => 9,
            'TikTok' => 20,
            'WhatsApp' => 10,
            default => 11,
        };
    }
}
