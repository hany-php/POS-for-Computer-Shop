<?php
/**
 * Authentication Class
 * كلاس المصادقة وإدارة الجلسات
 */
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Login with PIN (for cashiers)
    public function loginWithPin($pin) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE pin = ? AND is_active = 1", [$pin]);
        if ($user) {
            $this->createSession($user);
            return true;
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

    // Create session
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
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
