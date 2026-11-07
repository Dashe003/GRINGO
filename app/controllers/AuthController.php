<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    public function login(): void
    {
        $this->requireGuest();
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $identity = trim((string) ($_POST['identity'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $limit = $this->db->prepare('SELECT attempts, window_started_at FROM login_attempts WHERE identity_hash=:identity_hash AND ip_hash=:ip_hash');
            $identityHash = hash('sha256', mb_strtolower($identity));
            $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $limit->execute(['identity_hash'=>$identityHash,'ip_hash'=>$ipHash]);
            $attemptState = $limit->fetch();
            if ($attemptState && (int)$attemptState['attempts'] >= 5 && strtotime($attemptState['window_started_at']) > time() - 900) {
                $errors[] = 'Trop de tentatives. Réessayez dans 15 minutes.';
                render('auth/login', ['title' => 'Connexion', 'errors' => $errors]);
                return;
            }
            $stmt = $this->db->prepare('SELECT id, full_name, username, email, password_hash, theme, avatar_path FROM users WHERE username = :username OR email = :email LIMIT 1');
            $stmt->execute(['username' => $identity, 'email' => $identity]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                unset($user['password_hash']);
                $_SESSION['user'] = $user;
                $_SESSION['_token'] = bin2hex(random_bytes(32));
                $clear = $this->db->prepare('DELETE FROM login_attempts WHERE identity_hash=:identity_hash AND ip_hash=:ip_hash');
                $clear->execute(['identity_hash'=>$identityHash,'ip_hash'=>$ipHash]);
                redirect('dashboard');
            }
            $record = $this->db->prepare('INSERT INTO login_attempts (identity_hash,ip_hash,attempts,window_started_at) VALUES (:identity_hash,:ip_hash,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE),1,attempts+1), window_started_at=IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE),NOW(),window_started_at)');
            $record->execute(['identity_hash'=>$identityHash,'ip_hash'=>$ipHash]);
            // A generic message avoids revealing whether an account exists.
            $errors[] = 'Identifiant ou mot de passe incorrect.';
        }
        render('auth/login', ['title' => 'Connexion', 'errors' => $errors]);
    }

    public function register(): void
    {
        $this->requireGuest();
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $name = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Saisissez votre nom complet (120 caractères maximum).';
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) $errors[] = 'Le nom d’utilisateur doit contenir 3 à 40 lettres, chiffres, points, tirets ou tirets bas.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Saisissez une adresse e-mail valide.';
            if (strlen($password) < 10) $errors[] = 'Choisissez un mot de passe d’au moins 10 caractères.';
            if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) $errors[] = 'Les mots de passe ne correspondent pas.';
            if (!$errors) {
                $check = $this->db->prepare('SELECT id FROM users WHERE username = :username OR email = :email');
                $check->execute(['username' => $username, 'email' => $email]);
                if ($check->fetch()) $errors[] = 'Ce nom d’utilisateur ou cette adresse e-mail est déjà utilisé.';
            }
            if (!$errors) {
                $this->db->beginTransaction();
                try {
                    $stmt = $this->db->prepare('INSERT INTO users (full_name, username, email, password_hash) VALUES (:name, :username, :email, :password)');
                    $stmt->execute(['name' => $name, 'username' => $username, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
                    $id = (int) $this->db->lastInsertId();
                    $prefs = $this->db->prepare('INSERT INTO user_preferences (user_id) VALUES (:user_id)');
                    $prefs->execute(['user_id' => $id]);
                    $this->db->commit();
                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id' => $id, 'full_name' => $name, 'username' => $username, 'email' => $email, 'theme' => 'dark'];
                    $_SESSION['_token'] = bin2hex(random_bytes(32));
                    redirect('dashboard');
                } catch (PDOException $exception) {
                    $this->db->rollBack();
                    $errors[] = 'La création du compte a échoué. Vérifiez les informations saisies.';
                }
            }
        }
        render('auth/register', ['title' => 'Créer un compte', 'errors' => $errors]);
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login');
    }
}
