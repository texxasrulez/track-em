<?php
declare(strict_types=1);

namespace TrackEm\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../models/User.php';

use TrackEm\Core\Controller;
use TrackEm\Core\Security;
use TrackEm\Core\DB;
use TrackEm\Models\User;

final class AuthController extends Controller
{
    public function login(): void
    {
        Security::startSecureSession();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Security::verifyCsrf($_POST['csrf'] ?? '')) {
                $this->render('auth/login', ['error' => 'Bad CSRF token.']); return;
            }

            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            if ($u === '' || $p === '') {
                $this->render('auth/login', ['error' => 'Missing username or password.']); return;
            }

            $pdo = DB::pdo();
            $model = new User($pdo);

            // Prefer model method if present; otherwise fall back to direct SQL
            if (method_exists($model, 'findByUsername')) {
                $row = $model->findByUsername($u);
            } else {
                $st = $pdo->prepare("SELECT id, username, role, password_hash FROM users WHERE username = ? LIMIT 1");
                $st->execute([$u]);
                $row = $st->fetch() ?: null;
            }

            if (!$row || !password_verify($p, (string)$row['password_hash'])) {
                $this->render('auth/login', ['error' => 'Invalid credentials.']); return;
            }

            // rotate session id on login
            session_regenerate_id(true);
            $_SESSION['uid']      = (int)$row['id'];
            $_SESSION['username'] = (string)$row['username'];
            $_SESSION['role']     = (string)$row['role'];

            header('Location: ?p=admin'); return;
        }

        // GET
        $this->render('auth/login', []);
    }

    public function logout(): void
    {
        Security::startSecureSession();
        // nuke session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'] ?? '/', $params['domain'] ?? '', !empty($params['secure']), $params['httponly'] ?? true);
        }
        session_destroy();

        header('Location: ?p=login&msg=' . rawurlencode('You have been logged out.')); return;
    }
}
