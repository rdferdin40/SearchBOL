<?php
/**
 * Admin Authentication Handler
 */

namespace BOLSearch;

class AdminAuth
{
    private \PDO $db;
    private string $sessionName;
    private int $sessionLifetime;

    public function __construct(\PDO $db, string $sessionName, int $sessionLifetime)
    {
        $this->db = $db;
        $this->sessionName = $sessionName;
        $this->sessionLifetime = $sessionLifetime;

        $this->initSession();
    }

    /**
     * Initialize session
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->sessionName);
            session_set_cookie_params([
                'lifetime' => $this->sessionLifetime,
                'path' => '/',
                'secure' => false, // Set to true if using HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Attempt to log in
     */
    public function login(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('
            SELECT id, username, password_hash, is_active
            FROM admin_users
            WHERE username = ?
            LIMIT 1
        ');

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Set session
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_logged_in_at'] = time();

        // Regenerate session ID for security
        session_regenerate_id(true);

        return true;
    }

    /**
     * Log out
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['admin_logged_in_at'])) {
            $elapsed = time() - $_SESSION['admin_logged_in_at'];
            if ($elapsed > $this->sessionLifetime) {
                $this->logout();
                return false;
            }
        }

        return true;
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public function requireAuth(string $loginUrl = '/BOLSearch/public/admin/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /**
     * Get current admin user ID
     */
    public function getAdminId(): ?int
    {
        return $_SESSION['admin_id'] ?? null;
    }

    /**
     * Get current admin username
     */
    public function getAdminUsername(): ?string
    {
        return $_SESSION['admin_username'] ?? null;
    }

    /**
     * Change password
     */
    public function changePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('
            UPDATE admin_users SET password_hash = ? WHERE id = ?
        ');

        return $stmt->execute([$hash, $userId]);
    }

    /**
     * Generate password hash (utility method)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
