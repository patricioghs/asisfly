<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AutonomyRepository
{
    public function profile(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return $this->decorate([
                'company_id' => $companyId,
                'learning_progress' => 0,
                'mode' => 'supervised_learning',
                'approvals_count' => 0,
                'corrections_count' => 0,
                'autonomous_actions_count' => 0,
            ]);
        }

        $this->ensure($companyId);
        $statement = Database::connection()->prepare('SELECT * FROM company_ai_autonomy WHERE company_id = :company_id LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return $this->decorate($row);
    }

    public function requiresApproval(array $profile, string $riskLevel = 'medium'): bool
    {
        $progress = (int) ($profile['learning_progress'] ?? 0);
        if ($progress <= 25) {
            return true;
        }

        return match ($riskLevel) {
            'high' => $progress < 90,
            'medium' => $progress < 75,
            default => $progress < 50,
        };
    }

    public function recordSignal(int $companyId, string $signal): void
    {
        if (!$this->databaseReady()) {
            return;
        }

        $this->ensure($companyId);
        $delta = match ($signal) {
            'approved' => 2,
            'executed' => 3,
            'corrected' => 1,
            'rejected' => 1,
            'suggested' => 0,
            default => 0,
        };
        $approval = in_array($signal, ['approved', 'executed'], true) ? 1 : 0;
        $correction = in_array($signal, ['corrected', 'rejected'], true) ? 1 : 0;
        $autonomous = $signal === 'autonomous_executed' ? 1 : 0;

        $statement = Database::connection()->prepare('UPDATE company_ai_autonomy
            SET learning_progress = LEAST(100, learning_progress + :delta),
                mode = :mode,
                approvals_count = approvals_count + :approval,
                corrections_count = corrections_count + :correction,
                autonomous_actions_count = autonomous_actions_count + :autonomous,
                last_signal_at = CURRENT_TIMESTAMP
            WHERE company_id = :company_id');
        $current = $this->profile($companyId);
        $nextProgress = min(100, (int) $current['learning_progress'] + $delta);
        $statement->execute([
            'delta' => $delta,
            'mode' => $this->modeFor($nextProgress),
            'approval' => $approval,
            'correction' => $correction,
            'autonomous' => $autonomous,
            'company_id' => $companyId,
        ]);
    }

    private function ensure(int $companyId): void
    {
        Database::connection()->prepare('INSERT IGNORE INTO company_ai_autonomy (company_id, learning_progress, mode) VALUES (:company_id, 0, "supervised_learning")')
            ->execute(['company_id' => $companyId]);
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM company_ai_autonomy LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function decorate(array $row): array
    {
        $progress = (int) ($row['learning_progress'] ?? 0);
        if (
            $progress === 18
            && (int) ($row['approvals_count'] ?? 0) === 0
            && (int) ($row['corrections_count'] ?? 0) === 0
            && (int) ($row['autonomous_actions_count'] ?? 0) === 0
        ) {
            $progress = 0;
        }
        $mode = $this->modeFor($progress);
        $range = match ($mode) {
            'supervised_learning' => '0-25%',
            'copilot' => '26-60%',
            'controlled_autonomy' => '61-85%',
            default => '86-100%',
        };
        $label = match ($mode) {
            'supervised_learning' => 'Aprendizaje supervisado',
            'copilot' => 'Copiloto con aprobacion',
            'controlled_autonomy' => 'Autonomia controlada',
            default => 'Autonomo',
        };
        $description = match ($mode) {
            'supervised_learning' => 'AsisFly aprende como funciona la empresa. Todo lo que sugiera requiere revision, edicion o aprobacion humana.',
            'copilot' => 'AsisFly puede preparar respuestas y tareas con mas contexto, pero las acciones de impacto siguen pasando por aprobacion.',
            'controlled_autonomy' => 'AsisFly puede ejecutar acciones de bajo riesgo y pide aprobacion para ventas, mensajes, pagos o cambios sensibles.',
            default => 'AsisFly puede operar con autonomia segun reglas de la empresa, manteniendo auditoria y escalamiento humano.',
        };

        return [
            'company_id' => (int) ($row['company_id'] ?? 0),
            'learning_progress' => $progress,
            'mode' => $mode,
            'range' => $range,
            'label' => $label,
            'description' => $description,
            'approvals_count' => (int) ($row['approvals_count'] ?? 0),
            'corrections_count' => (int) ($row['corrections_count'] ?? 0),
            'autonomous_actions_count' => (int) ($row['autonomous_actions_count'] ?? 0),
            'requires_all_approval' => $progress <= 25,
        ];
    }

    private function modeFor(int $progress): string
    {
        return match (true) {
            $progress <= 25 => 'supervised_learning',
            $progress <= 60 => 'copilot',
            $progress <= 85 => 'controlled_autonomy',
            default => 'autonomous',
        };
    }
}
