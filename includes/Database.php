<?php
/**
 * Database Connection Class (Singleton)
 * كلاس الاتصال بقاعدة البيانات
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        $isNew = !file_exists(DB_PATH);
        
        $this->pdo = new PDO('sqlite:' . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        
        if ($isNew) {
            $this->initDatabase();
        } else {
            $this->runMigrations();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initDatabase() {
        $schemaFile = __DIR__ . '/../database/schema.sql';
        $seedFile = __DIR__ . '/../database/seed.sql';
        
        if (file_exists($schemaFile)) {
            $this->pdo->exec(file_get_contents($schemaFile));
        }
        if (file_exists($seedFile)) {
            $this->pdo->exec(file_get_contents($seedFile));
        }
        
        // Fix password hashes (SQL can't generate bcrypt)
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $this->pdo->exec("UPDATE users SET password = '$hash'");
    }

    private function runMigrations() {
        // Add discount column to maintenance_tickets if missing
        $cols = $this->pdo->query("PRAGMA table_info(maintenance_tickets)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('discount', $colNames)) {
            $this->pdo->exec("ALTER TABLE maintenance_tickets ADD COLUMN discount REAL DEFAULT 0");
        }
        
        // Add discount column to order_items if missing
        $cols = $this->pdo->query("PRAGMA table_info(order_items)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('discount', $colNames)) {
            $this->pdo->exec("ALTER TABLE order_items ADD COLUMN discount REAL DEFAULT 0");
        }

        // Ensure settings table exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Add return_invoice_number to orders if missing
        $cols = $this->pdo->query("PRAGMA table_info(orders)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('return_invoice_number', $colNames)) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN return_invoice_number TEXT DEFAULT NULL");
        }

        // Add customer_id to orders if missing
        if (!in_array('customer_id', $colNames)) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN customer_id INTEGER DEFAULT NULL");
        }

        // Create customers table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT,
            email TEXT,
            address TEXT,
            balance REAL DEFAULT 0,
            notes TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Create audit_log table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            user_name TEXT,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id INTEGER,
            details TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Helper: query with params
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Helper: fetch all rows
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    // Helper: fetch one row
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    // Helper: insert and return last ID
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    // Helper: generate unique order number
    public function generateOrderNumber() {
        $prefix = 'INV';
        $date = date('ymd');
        $last = $this->fetchOne("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1", ["$prefix-$date-%"]);
        if ($last) {
            $num = intval(substr($last['order_number'], -4)) + 1;
        } else {
            $num = 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $num);
    }

    // Helper: generate unique ticket number
    public function generateTicketNumber() {
        $prefix = 'MNT';
        $date = date('ymd');
        $last = $this->fetchOne("SELECT ticket_number FROM maintenance_tickets WHERE ticket_number LIKE ? ORDER BY id DESC LIMIT 1", ["$prefix-$date-%"]);
        if ($last) {
            $num = intval(substr($last['ticket_number'], -4)) + 1;
        } else {
            $num = 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $num);
    }

    // Helper: generate unique transaction number
    public function generateTransactionNumber() {
        $prefix = 'TRX';
        $date = date('ymd');
        $last = $this->fetchOne("SELECT transaction_number FROM used_device_purchases WHERE transaction_number LIKE ? ORDER BY id DESC LIMIT 1", ["$prefix-$date-%"]);
        if ($last) {
            $num = intval(substr($last['transaction_number'], -4)) + 1;
        } else {
            $num = 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $num);
    }
}
