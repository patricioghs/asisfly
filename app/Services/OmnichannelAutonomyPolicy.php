<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class OmnichannelAutonomyPolicy
{
    public function decide(int $companyId, array $account, array $message, array $autonomy, string $risk, int $confidence, bool $needsHuman): array
    {
        $setting = $this->channelSetting($companyId, (string) ($account['channel'] ?? $message['channel'] ?? 'all'));
        $mode = (string) ($setting['mode'] ?? 'manual');
        $minConfidence = (int) ($setting['min_confidence'] ?? 75);
        $progress = (int) ($autonomy['learning_progress'] ?? 0);
        $outboundEnabled = !empty($account['outbound_enabled']);
        $accountRequiresApproval = !empty($account['requires_approval']);
        $sensitiveRequiresApproval = !empty($setting['require_approval_for_sensitive']) && $risk !== 'low';

        $base = [
            'risk' => $risk,
            'confidence' => $confidence,
            'autonomy_mode' => $autonomy['mode'] ?? 'supervised_learning',
            'learning_progress' => $progress,
            'channel_mode' => $mode,
            'min_confidence' => $minConfidence,
            'channel_setting' => [
                'channel' => $setting['channel'] ?? 'all',
                'mode' => $mode,
                'min_confidence' => $minConfidence,
                'require_approval_for_sensitive' => !empty($setting['require_approval_for_sensitive']),
            ],
        ];

        if ($needsHuman) {
            return $base + [
                'mode' => 'human_required',
                'reason' => 'Mensaje sensible, ambiguo o con solicitud explicita de humano.',
            ];
        }

        if ($progress <= 25) {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'La empresa esta en aprendizaje supervisado 0-25%. Toda respuesta requiere aprobacion humana.',
            ];
        }

        if ($mode === 'manual') {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'El canal esta configurado en modo manual.',
            ];
        }

        if ($mode === 'assisted') {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'El canal esta en modo asistido: AsisFly prepara respuestas, pero el humano aprueba el envio.',
            ];
        }

        if (!$outboundEnabled) {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'La cuenta no tiene salida real activa.',
            ];
        }

        if ($accountRequiresApproval) {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'La cuenta conectada exige aprobacion humana.',
            ];
        }

        if ($sensitiveRequiresApproval) {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'El canal exige aprobacion para conversaciones comerciales o sensibles.',
            ];
        }

        if ($confidence < $minConfidence) {
            return $base + [
                'mode' => 'approval_required',
                'reason' => 'La confianza IA es menor al minimo configurado para este canal.',
            ];
        }

        return $base + [
            'mode' => 'auto_resolved',
            'reason' => 'Canal automatico, salida activa, riesgo bajo y confianza suficiente.',
        ];
    }

    private function channelSetting(int $companyId, string $channel): array
    {
        $normalized = $this->normalizeChannel($channel);
        $default = [
            'channel' => 'all',
            'mode' => 'manual',
            'min_confidence' => 75,
            'require_approval_for_sensitive' => 1,
            'status' => 'active',
        ];

        try {
            $statement = Database::connection()->prepare(
                'SELECT * FROM ai_channel_settings
                 WHERE company_id = :company_id
                   AND status = "active"
                   AND channel IN (:channel, "all")
                 ORDER BY FIELD(channel, :channel_order, "all")
                 LIMIT 1'
            );
            $statement->execute([
                'company_id' => $companyId,
                'channel' => $normalized,
                'channel_order' => $normalized,
            ]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) && $row ? $row : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        return match ($channel) {
            'correo', 'email', 'gmail', 'outlook' => 'email',
            'whatsapp', 'wsp' => 'whatsapp',
            'instagram' => 'instagram',
            'facebook', 'messenger' => 'facebook',
            default => $channel !== '' ? $channel : 'all',
        };
    }
}
