<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AuthRepository
{
    public function databaseReady(): bool
    {
        return Database::available();
    }

    public function findUserByEmail(string $email): ?array
    {
        $sql = "SELECT u.*, r.name AS role_name, r.permissions_json, c.name AS company_name,
                       c.country, c.currency, c.timezone AS company_timezone, c.locale AS company_locale,
                       p.name AS plan_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN companies c ON c.id = u.company_id
                LEFT JOIN plans p ON p.id = c.plan_id
                WHERE u.email = :email AND u.status = 'active'
                LIMIT 1";
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function createCompanyOwner(array $input): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $planName = $input['plan'] ?? 'Starter';
        $plan = $pdo->prepare('SELECT id FROM plans WHERE name = :name LIMIT 1');
        $plan->execute(['name' => $planName]);
        $planId = (int) ($plan->fetchColumn() ?: $pdo->query("SELECT id FROM plans WHERE name = 'Starter' LIMIT 1")->fetchColumn());
        $ownerRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Dueno de empresa' LIMIT 1")->fetchColumn();

        $company = $pdo->prepare('INSERT INTO companies (name, legal_name, country, currency, timezone, locale, plan_id, status) VALUES (:name, :legal_name, :country, :currency, :timezone, :locale, :plan_id, :status)');
        $company->execute([
            'name' => $input['company'],
            'legal_name' => $input['company'],
            'country' => $input['country'],
            'currency' => $input['currency'],
            'timezone' => $input['timezone'],
            'locale' => $input['locale'] ?? 'es_CL',
            'plan_id' => $planId,
            'status' => 'trial',
        ]);
        $companyId = (int) $pdo->lastInsertId();

        $user = $pdo->prepare('INSERT INTO users (company_id, role_id, name, email, password_hash, locale, timezone) VALUES (:company_id, :role_id, :name, :email, :password_hash, :locale, :timezone)');
        $user->execute([
            'company_id' => $companyId,
            'role_id' => $ownerRoleId,
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'locale' => $input['locale'] ?? 'es_CL',
            'timezone' => $input['timezone'],
        ]);

        $assistant = $pdo->prepare('INSERT INTO assistant_settings (company_id, assistant_name, tone, language, country, currency, work_hours, signature, rules, forbidden_words, required_phrases, human_escalation) VALUES (:company_id, :assistant_name, :tone, :language, :country, :currency, :work_hours, :signature, :rules, :forbidden_words, :required_phrases, :human_escalation)');
        $assistant->execute([
            'company_id' => $companyId,
            'assistant_name' => 'AsisFly',
            'tone' => 'Cercano y ejecutivo',
            'language' => 'Espanol latino',
            'country' => $input['country'],
            'currency' => $input['currency'],
            'work_hours' => 'Lunes a viernes, 09:00 a 18:00',
            'signature' => 'Equipo ' . $input['company'],
            'rules' => 'Pedir aprobacion antes de enviar correos, cotizaciones o mensajes comerciales.',
            'forbidden_words' => '',
            'required_phrases' => 'Quedo atento/a',
            'human_escalation' => 'Reclamos, descuentos especiales, riesgos legales o clientes molestos.',
        ]);

        foreach (['gmail', 'outlook', 'google_calendar', 'whatsapp_business', 'instagram', 'facebook', 'telegram'] as $provider) {
            $integration = $pdo->prepare('INSERT INTO integrations (company_id, provider, status, settings_json) VALUES (:company_id, :provider, :status, JSON_OBJECT())');
            $integration->execute([
                'company_id' => $companyId,
                'provider' => $provider,
                'status' => $provider === 'whatsapp_business' ? 'sandbox' : 'simulated',
            ]);
        }

        $pdo->commit();

        return $this->findUserByEmail($input['email']) ?? [];
    }

    public function createUser(int $companyId, array $input): void
    {
        $roleId = (int) ($input['role_id'] ?? 0);
        if ($roleId <= 0) {
            $statement = Database::connection()->prepare("SELECT id FROM roles WHERE name = 'Ejecutivo' LIMIT 1");
            $statement->execute();
            $roleId = (int) $statement->fetchColumn();
        }

        $statement = Database::connection()->prepare('INSERT INTO users (company_id, role_id, name, email, password_hash, locale, timezone, status) VALUES (:company_id, :role_id, :name, :email, :password_hash, :locale, :timezone, :status)');
        $statement->execute([
            'company_id' => $companyId,
            'role_id' => $roleId,
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'locale' => $input['locale'] ?? 'es_CL',
            'timezone' => $input['timezone'] ?? 'America/Santiago',
            'status' => $input['status'] ?? 'active',
        ]);
    }

    public function createPasswordReset(string $email): ?string
    {
        $user = $this->findUserByEmail($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $statement = Database::connection()->prepare('INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (:user_id, :email, :token_hash, DATE_ADD(NOW(), INTERVAL 45 MINUTE))');
        $statement->execute([
            'user_id' => (int) $user['id'],
            'email' => $email,
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        $statement = $pdo->prepare('SELECT * FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$reset) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $reset['user_id'],
        ]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')->execute(['id' => (int) $reset['id']]);

        $pdo->commit();
        return true;
    }

    public function toSession(array $row): array
    {
        $permissions = json_decode($row['permissions_json'] ?? '[]', true) ?: [];

        return [
            'user' => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role_name'],
                'permissions' => $permissions,
            ],
            'company' => [
                'id' => (int) ($row['company_id'] ?? 0),
                'name' => $row['company_name'] ?? 'AsisFly Global',
                'country' => $row['country'] ?? 'LatAm',
                'currency' => $row['currency'] ?? 'USD',
                'timezone' => $row['company_timezone'] ?? 'America/Santiago',
                'locale' => $row['company_locale'] ?? 'es_CL',
                'plan' => $row['plan_name'] ?? 'Enterprise',
            ],
        ];
    }
}
