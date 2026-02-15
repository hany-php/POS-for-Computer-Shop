<?php
/**
 * Authentication Class
 * كلاس المصادقة وإدارة الجلسات
 */
class Auth {
    private $db;
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Login with PIN (for cashiers)
    public function loginWithPin($pin) {
        $pin = (string)$pin;
        if ($pin === '') return false;

        // Check all active users with PIN credentials (hashed preferred).
        $users = $this->db->fetchAll(
            "SELECT * FROM users
             WHERE is_active = 1
             AND ((pin_hash IS NOT NULL AND pin_hash <> '') OR (pin IS NOT NULL AND pin <> ''))"
        );
        foreach ($users as $user) {
            if ($this->verifyPinForUser($user, $pin)) {
                $this->createSession($user);
                return true;
            }
        }
        return false;
    }

    // Login with username and password (for admin/tech)
    public function loginWithPassword($username, $password) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        if ($user && password_verify($password, $user['password'])) {
            $this->createSession($user);
            return true;
        }
        return false;
    }

    // Check rate limit status for login key (usually based on IP + login type)
    public function getLoginThrottleStatus($rateKey) {
        $row = $this->db->fetchOne(
            "SELECT failed_attempts, locked_until FROM login_rate_limits WHERE rate_key = ?",
            [$rateKey]
        );

        if (!$row || empty($row['locked_until'])) {
            return ['allowed' => true, 'seconds_remaining' => 0];
        }

        $lockUntilTs = strtotime($row['locked_until']);
        $nowTs = time();
        if ($lockUntilTs && $lockUntilTs > $nowTs) {
            return ['allowed' => false, 'seconds_remaining' => $lockUntilTs - $nowTs];
        }

        // Lock expired: reset attempts
        $this->clearLoginThrottle($rateKey);
        return ['allowed' => true, 'seconds_remaining' => 0];
    }

    public function registerLoginFailure($rateKey) {
        $row = $this->db->fetchOne(
            "SELECT failed_attempts FROM login_rate_limits WHERE rate_key = ?",
            [$rateKey]
        );
        $attempts = ($row ? intval($row['failed_attempts']) : 0) + 1;
        $lockedUntil = null;
        if ($attempts >= self::LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (self::LOGIN_LOCK_MINUTES * 60));
        }

        $this->db->query(
            "INSERT INTO login_rate_limits (rate_key, failed_attempts, locked_until, updated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT(rate_key) DO UPDATE SET
             failed_attempts = excluded.failed_attempts,
             locked_until = excluded.locked_until,
             updated_at = CURRENT_TIMESTAMP",
            [$rateKey, $attempts, $lockedUntil]
        );
    }

    public function clearLoginThrottle($rateKey) {
        $this->db->query("DELETE FROM login_rate_limits WHERE rate_key = ?", [$rateKey]);
    }

    // Verify current logged-in user credentials to unlock inactivity screen.
    // Accept either PIN or password for the same logged-in user.
    public function unlockSession($secret) {
        if (!self::isLoggedIn()) return false;
        $userId = intval($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) return false;

        $user = $this->db->fetchOne("SELECT id, role, pin, pin_hash, password, is_active FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$user || intval($user['is_active']) !== 1) return false;

        $secret = (string)$secret;
        if ($secret === '') return false;

        // Try PIN first (if available), then account password.
        if ($this->verifyPinForUser($user, $secret)) {
            return true;
        }

        return password_verify($secret, (string)($user['password'] ?? ''));
    }

    private function verifyPinForUser($user, $pin) {
        $pin = (string)$pin;
        $hash = (string)($user['pin_hash'] ?? '');
        if ($hash !== '' && password_verify($pin, $hash)) {
            return true;
        }

        // Backward compatibility for old plain PIN records, auto-migrate on success.
        $plainPin = (string)($user['pin'] ?? '');
        if ($plainPin !== '' && hash_equals($plainPin, $pin)) {
            $newHash = password_hash($pin, PASSWORD_DEFAULT);
            $this->db->query("UPDATE users SET pin_hash = ?, pin = NULL WHERE id = ?", [$newHash, $user['id']]);
            return true;
        }

        return false;
    }

    // Create session
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['screen_locked'] = false;
    }

    // Check if logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Get current user
    public static function user() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }

    // Check role
    public static function hasRole($role) {
        if (!self::isLoggedIn()) return false;
        if (is_array($role)) {
            return in_array($_SESSION['role'], $role);
        }
        return $_SESSION['role'] === $role;
    }

    // Require login
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    // Require specific role
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header('Location: pos.php');
            exit;
        }
    }

    // Logout
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Get role name in Arabic
    public static function getRoleNameAr($role) {
        $roles = [
            'admin' => 'مدير النظام',
            'cashier' => 'كاشير',
            'technician' => 'فني صيانة'
        ];
        return $roles[$role] ?? $role;
    }
}
