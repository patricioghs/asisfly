<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RateLimiter;
use App\Repositories\AuthRepository;

final class AuthController extends Controller
{
    public function login(): void
    {
        $this->view('auth/login', ['title' => 'Ingresar']);
    }

    public function authenticate(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $auth = new AuthRepository();
        $key = 'login:' . strtolower($email) . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'local');

        if (!(new RateLimiter())->hit($key, 6, 300)) {
            $_SESSION['flash_error'] = 'Demasiados intentos. Espera unos minutos antes de intentar de nuevo.';
            $this->redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $_SESSION['flash_error'] = 'Ingresa correo y contrasena validos.';
            $this->redirect('/login');
        }

        if ($auth->databaseReady()) {
            $row = $auth->findUserByEmail($email);
            if (!$row || !password_verify($password, $row['password_hash'])) {
                $_SESSION['flash_error'] = 'Credenciales invalidas.';
                $this->redirect('/login');
            }

            $session = $auth->toSession($row);
            $_SESSION['user'] = $session['user'];
            $_SESSION['company'] = $session['company'];
            $this->redirect('/dashboard');
        }

        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin AsisFly',
            'email' => $email ?: 'admin@asisfly.ai',
            'role' => 'Superadmin',
            'permissions' => ['*'],
        ];
        $_SESSION['company'] = $this->currentCompany();
        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        $this->view('auth/register', ['title' => 'Crear empresa']);
    }

    public function forgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Recuperar contrasena']);
    }

    public function sendReset(): void
    {
        $email = trim($_POST['email'] ?? '');
        $auth = new AuthRepository();
        $key = 'forgot:' . strtolower($email) . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'local');

        if (!(new RateLimiter())->hit($key, 3, 600)) {
            $_SESSION['flash_success'] = 'Si el correo existe, se genero un enlace de recuperacion.';
            $this->redirect('/forgot-password');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_success'] = 'Si el correo existe, se genero un enlace de recuperacion.';
            $this->redirect('/forgot-password');
        }

        if ($auth->databaseReady()) {
            $token = $auth->createPasswordReset($email);
            $_SESSION['flash_success'] = 'Si el correo existe, se genero un enlace de recuperacion.';
            if ($token) {
                $_SESSION['reset_link'] = url('/reset-password?token=' . $token);
            }
        } else {
            $_SESSION['flash_success'] = 'Modo demo: recuperacion simulada.';
        }

        $this->redirect('/forgot-password');
    }

    public function reset(): void
    {
        $this->view('auth/reset', ['title' => 'Restablecer contrasena', 'token' => $_GET['token'] ?? '']);
    }

    public function updatePassword(): void
    {
        $auth = new AuthRepository();
        $password = (string) ($_POST['password'] ?? '');
        $ok = $auth->databaseReady() && strlen($password) >= 8 && $auth->resetPassword((string) ($_POST['token'] ?? ''), $password);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Contrasena actualizada. Ya puedes ingresar.' : 'El enlace no es valido o la contrasena es muy corta.';
        $this->redirect($ok ? '/login' : '/reset-password?token=' . urlencode((string) ($_POST['token'] ?? '')));
    }

    public function store(): void
    {
        $input = [
            'name' => trim($_POST['name'] ?? 'Nuevo usuario'),
            'email' => trim($_POST['email'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
            'company' => trim($_POST['company'] ?? 'Nueva Empresa'),
            'country' => trim($_POST['country'] ?? 'Chile'),
            'currency' => trim($_POST['currency'] ?? 'CLP'),
            'timezone' => trim($_POST['timezone'] ?? 'America/Santiago'),
            'locale' => trim($_POST['locale'] ?? 'es_CL'),
        ];
        $auth = new AuthRepository();
        if ($input['name'] === '' || $input['company'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['password']) < 8) {
            $_SESSION['flash_error'] = 'Completa nombre, empresa, email valido y contrasena de al menos 8 caracteres.';
            $this->redirect('/register');
        }

        if ($auth->databaseReady()) {
            $session = $auth->toSession($auth->createCompanyOwner($input));
            $_SESSION['user'] = $session['user'];
            $_SESSION['company'] = $session['company'];
            $this->redirect('/dashboard');
        }

        $_SESSION['user'] = [
            'id' => 2,
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => 'Dueno de empresa',
            'permissions' => ['company.manage', 'users.manage', 'assistant.manage', 'billing.manage', 'crm.manage', 'quotes.manage', 'documents.manage', 'chat.use', 'dashboard.view'],
        ];
        $_SESSION['company'] = [
            'id' => random_int(10, 999),
            'name' => $input['company'],
            'country' => $input['country'],
            'currency' => $input['currency'],
            'timezone' => $input['timezone'],
            'locale' => $input['locale'],
            'plan' => 'Starter',
        ];
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/login');
    }
}
