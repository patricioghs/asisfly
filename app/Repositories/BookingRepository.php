<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class BookingRepository
{
    public function overview(int $companyId, array $company, string $date = ''): array
    {
        $settings = $this->ensureSettings($companyId, $company);
        $resourceId = (int) ($_GET['resource_id'] ?? 0);
        $resources = $this->resources($companyId);
        $resourceId = $this->validResourceId($resources, $resourceId) ?: (int) ($resources[0]['id'] ?? 0);
        $date = $this->date($date, (string) $settings['timezone']);

        return [
            'settings' => $settings,
            'resources' => $resources,
            'selectedResourceId' => $resourceId,
            'selectedDate' => $date,
            'days' => $this->days($date, (string) $settings['timezone']),
            'appointments' => $this->appointments($companyId, $resourceId, $date),
            'rules' => $this->rules($companyId, $resourceId),
            'blocks' => $this->blocks($companyId, $resourceId, $date),
            'metrics' => $this->metrics($companyId, (string) $settings['timezone']),
            'publicUrl' => url('/reserve?space=' . rawurlencode((string) $settings['public_token'])),
        ];
    }

    public function saveSettings(int $companyId, array $company, array $input): void
    {
        $existing = $this->ensureSettings($companyId, $company);
        Database::connection()->prepare(
            'UPDATE booking_settings SET public_title = :title, public_description = :description, timezone = :timezone, default_duration_minutes = :duration, minimum_notice_hours = :notice, confirmation_mode = :mode, public_enabled = :enabled WHERE company_id = :company_id'
        )->execute([
            'title' => $this->text($input['public_title'] ?? '', 180) ?: 'Reserva una hora',
            'description' => $this->text($input['public_description'] ?? '', 500) ?: null,
            'timezone' => $this->validTimezone((string) ($input['timezone'] ?? $existing['timezone'])),
            'duration' => $this->range((int) ($input['default_duration_minutes'] ?? 60), 10, 480, 60),
            'notice' => $this->range((int) ($input['minimum_notice_hours'] ?? 2), 0, 720, 2),
            'mode' => in_array($input['confirmation_mode'] ?? '', ['automatic', 'manual'], true) ? $input['confirmation_mode'] : 'manual',
            'enabled' => !empty($input['public_enabled']) ? 1 : 0,
            'company_id' => $companyId,
        ]);
    }

    public function addResource(int $companyId, int $userId, array $input): bool
    {
        $name = $this->text($input['name'] ?? '', 180);
        if ($name === '') {
            return false;
        }
        Database::connection()->prepare(
            'INSERT INTO booking_resources (company_id, user_id, name, resource_type, duration_minutes, is_active) VALUES (:company_id, :user_id, :name, :type, :duration, TRUE)'
        )->execute([
            'company_id' => $companyId,
            'user_id' => $userId ?: null,
            'name' => $name,
            'type' => in_array($input['resource_type'] ?? '', ['staff', 'room', 'service', 'equipment', 'general'], true) ? $input['resource_type'] : 'staff',
            'duration' => !empty($input['duration_minutes']) ? $this->range((int) $input['duration_minutes'], 10, 480, 60) : null,
        ]);
        return true;
    }

    public function saveRule(int $companyId, int $resourceId, array $input): bool
    {
        if (!$this->ownsResource($companyId, $resourceId)) {
            return false;
        }
        $day = (int) ($input['day_of_week'] ?? 1);
        $start = (string) ($input['starts_at'] ?? '09:00');
        $end = (string) ($input['ends_at'] ?? '18:00');
        if ($day < 1 || $day > 7 || !$this->validTime($start) || !$this->validTime($end) || $start >= $end) {
            return false;
        }
        Database::connection()->prepare(
            'INSERT INTO booking_availability_rules (company_id, resource_id, day_of_week, starts_at, ends_at, is_active) VALUES (:company_id, :resource_id, :day, :starts_at, :ends_at, TRUE)'
        )->execute(['company_id' => $companyId, 'resource_id' => $resourceId, 'day' => $day, 'starts_at' => $start, 'ends_at' => $end]);
        return true;
    }

    public function deleteRule(int $companyId, int $ruleId): void
    {
        Database::connection()->prepare('DELETE FROM booking_availability_rules WHERE company_id = :company_id AND id = :id')->execute(['company_id' => $companyId, 'id' => $ruleId]);
    }

    public function addBlock(int $companyId, int $userId, array $input, string $timezone): bool
    {
        $resourceId = (int) ($input['resource_id'] ?? 0);
        $starts = $this->localDateTime((string) ($input['starts_at'] ?? ''), $timezone);
        $ends = $this->localDateTime((string) ($input['ends_at'] ?? ''), $timezone);
        if (!$this->ownsResource($companyId, $resourceId) || !$starts || !$ends || $ends <= $starts) {
            return false;
        }
        Database::connection()->prepare(
            'INSERT INTO booking_blocks (company_id, resource_id, starts_at, ends_at, reason, created_by) VALUES (:company_id, :resource_id, :starts_at, :ends_at, :reason, :created_by)'
        )->execute([
            'company_id' => $companyId,
            'resource_id' => $resourceId,
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'reason' => $this->text($input['reason'] ?? '', 255) ?: null,
            'created_by' => $userId ?: null,
        ]);
        return true;
    }

    public function createInternal(int $companyId, int $userId, array $input, array $company): array
    {
        $settings = $this->ensureSettings($companyId, $company);
        return $this->createAppointment($companyId, $userId, $input, (string) $settings['timezone'], 'internal', 'confirmed');
    }

    public function updateStatus(int $companyId, int $appointmentId, string $status): bool
    {
        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'], true)) {
            return false;
        }
        Database::connection()->prepare('UPDATE booking_appointments SET status = :status WHERE company_id = :company_id AND id = :id')->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $appointmentId,
        ]);
        return true;
    }

    public function publicSpace(string $token, string $date = '', int $resourceId = 0): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, c.name AS company_name FROM booking_settings s INNER JOIN companies c ON c.id = s.company_id WHERE s.public_token = :token AND s.public_enabled = TRUE AND c.status IN ("active", "trial") LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $settings = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return null;
        }
        $resources = $this->resources((int) $settings['company_id']);
        $resourceId = $this->validResourceId($resources, $resourceId) ?: (int) ($resources[0]['id'] ?? 0);
        $date = $this->date($date, (string) $settings['timezone']);
        return [
            'settings' => $settings,
            'resources' => $resources,
            'selectedResourceId' => $resourceId,
            'selectedDate' => $date,
            'days' => $this->days($date, (string) $settings['timezone']),
            'slots' => $resourceId > 0 ? $this->availableSlots((int) $settings['company_id'], $resourceId, $date, $settings) : [],
        ];
    }

    public function createPublic(string $token, array $input): array
    {
        $space = $this->publicSpace($token, (string) ($input['date'] ?? ''), (int) ($input['resource_id'] ?? 0));
        if (!$space) {
            return ['ok' => false, 'message' => 'El enlace de reservas no esta disponible.'];
        }
        $settings = $space['settings'];
        $input['starts_at'] = trim((string) ($input['date'] ?? '')) . ' ' . trim((string) ($input['slot'] ?? ''));
        $input['service_name'] = $this->text($input['service_name'] ?? '', 180);
        $input['customer_name'] = $this->text($input['customer_name'] ?? '', 180);
        if ($input['customer_name'] === '') {
            return ['ok' => false, 'message' => 'Indica tu nombre para reservar.'];
        }
        return $this->createAppointment((int) $settings['company_id'], 0, $input, (string) $settings['timezone'], 'public', (string) $settings['confirmation_mode'] === 'automatic' ? 'confirmed' : 'pending');
    }

    public function resources(int $companyId): array
    {
        $statement = Database::connection()->prepare('SELECT r.*, u.name AS user_name FROM booking_resources r LEFT JOIN users u ON u.id = r.user_id WHERE r.company_id = :company_id AND r.is_active = TRUE ORDER BY r.id');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rules(int $companyId, int $resourceId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM booking_availability_rules WHERE company_id = :company_id AND resource_id = :resource_id AND is_active = TRUE ORDER BY day_of_week, starts_at');
        $statement->execute(['company_id' => $companyId, 'resource_id' => $resourceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensureSettings(int $companyId, array $company): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM booking_settings WHERE company_id = :company_id LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $settings = $statement->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            return $settings;
        }
        $token = bin2hex(random_bytes(18));
        $timezone = $this->validTimezone((string) ($company['timezone'] ?? 'America/Santiago'));
        Database::connection()->prepare('INSERT INTO booking_settings (company_id, public_token, public_title, timezone) VALUES (:company_id, :token, :title, :timezone)')->execute([
            'company_id' => $companyId,
            'token' => $token,
            'title' => 'Reserva una hora con ' . $this->text($company['name'] ?? 'nosotros', 120),
            'timezone' => $timezone,
        ]);
        Database::connection()->prepare('INSERT INTO booking_resources (company_id, name, resource_type) VALUES (:company_id, :name, "general")')->execute([
            'company_id' => $companyId,
            'name' => 'Agenda principal',
        ]);
        return $this->ensureSettings($companyId, $company);
    }

    private function appointments(int $companyId, int $resourceId, string $date): array
    {
        $statement = Database::connection()->prepare('SELECT a.*, r.name AS resource_name FROM booking_appointments a INNER JOIN booking_resources r ON r.id = a.resource_id WHERE a.company_id = :company_id AND (:resource_id = 0 OR a.resource_id = :resource_id) AND DATE(a.starts_at) BETWEEN DATE_SUB(:date, INTERVAL 1 DAY) AND DATE_ADD(:date, INTERVAL 13 DAY) ORDER BY a.starts_at');
        $statement->execute(['company_id' => $companyId, 'resource_id' => $resourceId, 'date' => $date]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function blocks(int $companyId, int $resourceId, string $date): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM booking_blocks WHERE company_id = :company_id AND resource_id = :resource_id AND DATE(starts_at) BETWEEN DATE_SUB(:date, INTERVAL 1 DAY) AND DATE_ADD(:date, INTERVAL 13 DAY) ORDER BY starts_at');
        $statement->execute(['company_id' => $companyId, 'resource_id' => $resourceId, 'date' => $date]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function metrics(int $companyId, string $timezone): array
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');
        $pdo = Database::connection();
        $scalar = function (string $sql, array $params = []) use ($pdo): int {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        };
        return [
            ['label' => 'Reservas hoy', 'value' => $scalar('SELECT COUNT(*) FROM booking_appointments WHERE company_id = :company_id AND DATE(starts_at) = :date AND status NOT IN ("cancelled", "no_show")', ['company_id' => $companyId, 'date' => $today]), 'icon' => 'bi-calendar-check'],
            ['label' => 'Por confirmar', 'value' => $scalar('SELECT COUNT(*) FROM booking_appointments WHERE company_id = :company_id AND status = "pending"', ['company_id' => $companyId]), 'icon' => 'bi-hourglass-split'],
            ['label' => 'Próximos 7 días', 'value' => $scalar('SELECT COUNT(*) FROM booking_appointments WHERE company_id = :company_id AND starts_at >= NOW() AND starts_at < DATE_ADD(NOW(), INTERVAL 7 DAY) AND status NOT IN ("cancelled", "no_show")', ['company_id' => $companyId]), 'icon' => 'bi-calendar-week'],
        ];
    }

    private function createAppointment(int $companyId, int $userId, array $input, string $timezone, string $source, string $status): array
    {
        $resourceId = (int) ($input['resource_id'] ?? 0);
        $starts = $this->localDateTime((string) ($input['starts_at'] ?? ''), $timezone);
        $name = $this->text($input['customer_name'] ?? '', 180);
        if (!$this->ownsResource($companyId, $resourceId) || !$starts || $name === '') {
            return ['ok' => false, 'message' => 'Completa responsable, fecha, hora y nombre del cliente.'];
        }
        $settings = $this->settings($companyId);
        $duration = $this->resourceDuration($companyId, $resourceId, (int) ($settings['default_duration_minutes'] ?? 60));
        $ends = $starts->add(new DateInterval('PT' . $duration . 'M'));
        if ($source === 'public' && !$this->slotIsAvailable($companyId, $resourceId, $starts, $ends, $settings)) {
            return ['ok' => false, 'message' => 'Ese horario ya no esta disponible. Elige otro bloque.'];
        }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $conflict = $pdo->prepare('SELECT COUNT(*) FROM booking_appointments WHERE company_id = :company_id AND resource_id = :resource_id AND status NOT IN ("cancelled", "no_show") AND starts_at < :ends_at AND ends_at > :starts_at FOR UPDATE');
            $conflict->execute(['company_id' => $companyId, 'resource_id' => $resourceId, 'starts_at' => $starts->format('Y-m-d H:i:s'), 'ends_at' => $ends->format('Y-m-d H:i:s')]);
            if ((int) $conflict->fetchColumn() > 0) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Ese horario acaba de ser reservado. Elige otro.'];
            }
            $pdo->prepare('INSERT INTO booking_appointments (public_id, company_id, resource_id, customer_name, customer_email, customer_phone, service_name, notes, starts_at, ends_at, status, source, created_by) VALUES (:public_id, :company_id, :resource_id, :name, :email, :phone, :service, :notes, :starts_at, :ends_at, :status, :source, :created_by)')->execute([
                'public_id' => strtoupper(bin2hex(random_bytes(10))),
                'company_id' => $companyId,
                'resource_id' => $resourceId,
                'name' => $name,
                'email' => filter_var($input['customer_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                'phone' => $this->text($input['customer_phone'] ?? '', 60) ?: null,
                'service' => $this->text($input['service_name'] ?? '', 180) ?: null,
                'notes' => $this->text($input['notes'] ?? '', 3000) ?: null,
                'starts_at' => $starts->format('Y-m-d H:i:s'),
                'ends_at' => $ends->format('Y-m-d H:i:s'),
                'status' => $status,
                'source' => $source,
                'created_by' => $userId ?: null,
            ]);
            $pdo->commit();
            return ['ok' => true, 'message' => $status === 'confirmed' ? 'Hora reservada y confirmada.' : 'Solicitud recibida. Te confirmaremos la hora pronto.'];
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'No se pudo guardar la reserva. Intenta nuevamente.'];
        }
    }

    private function availableSlots(int $companyId, int $resourceId, string $date, array $settings): array
    {
        $timezone = new DateTimeZone((string) $settings['timezone']);
        $day = (int) (new DateTimeImmutable($date, $timezone))->format('N');
        $rules = array_values(array_filter($this->rules($companyId, $resourceId), fn (array $rule): bool => (int) $rule['day_of_week'] === $day));
        $duration = $this->resourceDuration($companyId, $resourceId, (int) $settings['default_duration_minutes']);
        $slots = [];
        foreach ($rules as $rule) {
            $cursor = new DateTimeImmutable($date . ' ' . substr((string) $rule['starts_at'], 0, 5), $timezone);
            $end = new DateTimeImmutable($date . ' ' . substr((string) $rule['ends_at'], 0, 5), $timezone);
            while ($cursor->add(new DateInterval('PT' . $duration . 'M')) <= $end) {
                $slotEnd = $cursor->add(new DateInterval('PT' . $duration . 'M'));
                if ($this->slotIsAvailable($companyId, $resourceId, $cursor, $slotEnd, $settings)) {
                    $slots[] = $cursor->format('H:i');
                }
                $cursor = $slotEnd;
            }
        }
        return array_values(array_unique($slots));
    }

    private function slotIsAvailable(int $companyId, int $resourceId, DateTimeImmutable $starts, DateTimeImmutable $ends, array $settings): bool
    {
        $notice = max(0, (int) ($settings['minimum_notice_hours'] ?? 0));
        if ($starts < (new DateTimeImmutable('now', $starts->getTimezone()))->add(new DateInterval('PT' . $notice . 'H'))) {
            return false;
        }
        $params = ['company_id' => $companyId, 'resource_id' => $resourceId, 'starts_at' => $starts->format('Y-m-d H:i:s'), 'ends_at' => $ends->format('Y-m-d H:i:s')];
        $pdo = Database::connection();
        foreach ([
            'SELECT COUNT(*) FROM booking_appointments WHERE company_id = :company_id AND resource_id = :resource_id AND status NOT IN ("cancelled", "no_show") AND starts_at < :ends_at AND ends_at > :starts_at',
            'SELECT COUNT(*) FROM booking_blocks WHERE company_id = :company_id AND resource_id = :resource_id AND starts_at < :ends_at AND ends_at > :starts_at',
        ] as $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            if ((int) $statement->fetchColumn() > 0) {
                return false;
            }
        }
        return true;
    }

    private function settings(int $companyId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM booking_settings WHERE company_id = :company_id');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function ownsResource(int $companyId, int $resourceId): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM booking_resources WHERE company_id = :company_id AND id = :id AND is_active = TRUE');
        $statement->execute(['company_id' => $companyId, 'id' => $resourceId]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function resourceDuration(int $companyId, int $resourceId, int $fallback): int
    {
        $statement = Database::connection()->prepare('SELECT duration_minutes FROM booking_resources WHERE company_id = :company_id AND id = :id');
        $statement->execute(['company_id' => $companyId, 'id' => $resourceId]);
        return $this->range((int) ($statement->fetchColumn() ?: $fallback), 10, 480, 60);
    }

    private function validResourceId(array $resources, int $resourceId): int
    {
        foreach ($resources as $resource) {
            if ((int) $resource['id'] === $resourceId) {
                return $resourceId;
            }
        }
        return 0;
    }

    private function days(string $date, string $timezone): array
    {
        $start = new DateTimeImmutable($date, new DateTimeZone($timezone));
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->add(new DateInterval('P' . $i . 'D'));
            $days[] = ['date' => $day->format('Y-m-d'), 'day' => ucfirst($day->format('D')), 'number' => $day->format('d'), 'month' => ucfirst($day->format('M'))];
        }
        return $days;
    }

    private function date(string $date, string $timezone): string
    {
        try {
            return (new DateTimeImmutable($date ?: 'today', new DateTimeZone($timezone)))->format('Y-m-d');
        } catch (Throwable) {
            return (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
        }
    }

    private function localDateTime(string $value, string $timezone): ?DateTimeImmutable
    {
        try {
            return $value !== '' ? new DateTimeImmutable(str_replace('T', ' ', $value), new DateTimeZone($timezone)) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'America/Santiago';
    }

    private function validTime(string $value): bool
    {
        return (bool) preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', substr($value, 0, 5));
    }

    private function text(mixed $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }

    private function range(int $value, int $min, int $max, int $fallback): int
    {
        return $value >= $min && $value <= $max ? $value : $fallback;
    }
}
