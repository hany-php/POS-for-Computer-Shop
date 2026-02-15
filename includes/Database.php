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
        $this->backfillPinHashes();
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

        // Add pin_hash to users if missing
        $cols = $this->pdo->query("PRAGMA table_info(users)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('pin_hash', $colNames)) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN pin_hash TEXT DEFAULT NULL");
        }

        // Ensure settings table exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('tax_enabled', '1')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('hijri_date_enabled', '1')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('backup_auto_enabled', '1')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('backup_frequency_hours', '24')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('backup_keep_count', '20')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('last_auto_backup_at', '')");

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

        // Create login rate-limits table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
            rate_key TEXT PRIMARY KEY,
            failed_attempts INTEGER DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Suspended (saved) POS orders
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS suspended_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            suspend_number TEXT UNIQUE NOT NULL,
            user_id INTEGER,
            customer_id INTEGER,
            customer_name TEXT,
            customer_phone TEXT,
            state_json TEXT NOT NULL,
            total REAL DEFAULT 0,
            status TEXT DEFAULT 'open' CHECK(status IN ('open','closed','cancelled')),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        )");

        // Customer due payments (collection of outstanding invoice balances)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS customer_due_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            customer_id INTEGER,
            amount REAL NOT NULL DEFAULT 0,
            payment_method TEXT DEFAULT 'cash',
            before_due REAL DEFAULT 0,
            after_due REAL DEFAULT 0,
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");

        // Accounting core tables
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('asset','liability','equity','revenue','expense')),
            is_active INTEGER DEFAULT 1,
            is_system INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS journal_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_number TEXT UNIQUE NOT NULL,
            ref_type TEXT,
            ref_id INTEGER,
            ref_number TEXT,
            description TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS journal_lines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_id INTEGER NOT NULL,
            account_code TEXT NOT NULL,
            debit REAL DEFAULT 0,
            credit REAL DEFAULT 0,
            line_note TEXT,
            FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
        )");

        // Per-user policy: does cycle close require manager review
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_finance_policies (
            user_id INTEGER PRIMARY KEY,
            require_manager_review INTEGER DEFAULT 1,
            updated_by INTEGER,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Cashier cycle summary and approval state
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS cashier_cycles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            status TEXT DEFAULT 'open' CHECK(status IN ('open','pending_review','closed')),
            opening_balance REAL DEFAULT 0,
            cash_sales_total REAL DEFAULT 0,
            card_sales_total REAL DEFAULT 0,
            transfer_sales_total REAL DEFAULT 0,
            total_sales REAL DEFAULT 0,
            refunds_total REAL DEFAULT 0,
            net_total REAL DEFAULT 0,
            orders_count INTEGER DEFAULT 0,
            close_request_note TEXT,
            close_requested_at DATETIME,
            reviewed_by INTEGER,
            reviewed_at DATETIME,
            review_note TEXT,
            opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        // Expense categories and expenses
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            expense_date DATE NOT NULL,
            category_id INTEGER,
            amount REAL NOT NULL DEFAULT 0,
            payment_method TEXT DEFAULT 'cash',
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES expense_categories(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");

        // Treasury movements (cash/bank)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS treasury_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            txn_type TEXT NOT NULL CHECK(txn_type IN ('in','out')),
            account_code TEXT NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            source TEXT,
            payment_method TEXT DEFAULT 'cash',
            ref_type TEXT,
            ref_id INTEGER,
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Suppliers and purchasing
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT,
            email TEXT,
            address TEXT,
            notes TEXT,
            current_balance REAL DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS purchase_invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_number TEXT UNIQUE NOT NULL,
            supplier_id INTEGER NOT NULL,
            invoice_date DATE NOT NULL,
            total_amount REAL DEFAULT 0,
            paid_amount REAL DEFAULT 0,
            due_amount REAL DEFAULT 0,
            payment_method TEXT DEFAULT 'cash',
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending','partial','paid')),
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_invoice_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            unit_cost REAL NOT NULL DEFAULT 0,
            total_cost REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS supplier_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            supplier_id INTEGER NOT NULL,
            purchase_invoice_id INTEGER,
            payment_date DATE NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            payment_method TEXT DEFAULT 'cash',
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");

        // Maintenance issue templates
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_issue_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            device_type TEXT DEFAULT '',
            problem_text TEXT NOT NULL,
            default_estimated_cost REAL DEFAULT 0,
            sort_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Maintenance ticket timeline history
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ticket_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            action_label TEXT NOT NULL,
            notes TEXT,
            changed_by INTEGER,
            changed_by_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES maintenance_tickets(id) ON DELETE CASCADE
        )");

        // Seed common templates if empty
        $templateCount = intval(($this->fetchOne("SELECT COUNT(*) AS cnt FROM maintenance_issue_templates")['cnt'] ?? 0));
        if ($templateCount === 0) {
            $this->query(
                "INSERT INTO maintenance_issue_templates (title, device_type, problem_text, default_estimated_cost, sort_order) VALUES
                ('تنظيف داخلي وتغيير معجون', 'لابتوب', 'سخونة عالية مع صوت مروحة مرتفع ويحتاج تنظيف داخلي وتغيير معجون حراري.', 120, 1),
                ('فحص باور سبلاي', 'كمبيوتر مكتبي', 'الجهاز لا يعمل ويحتاج فحص مزود الطاقة وخطوط الكهرباء الداخلية.', 80, 2),
                ('تغيير هارد إلى SSD', 'لابتوب', 'بطء شديد في الإقلاع ويُوصى بتركيب SSD ونقل النظام والبيانات.', 250, 3),
                ('مشكلة شبكة واتصال', 'جهاز شبكة', 'انقطاع متكرر في الشبكة ويحتاج فحص الإعدادات والكابلات والتحديث.', 90, 4),
                ('فحص DVR/NVR والكاميرات', 'DVR/NVR', 'صورة متقطعة أو عدم تسجيل ويحتاج فحص القنوات والتخزين والطاقة.', 150, 5)"
            );
        }

        // Performance indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_active_category_qty ON products (is_active, category_id, quantity)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_barcode ON products (barcode)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_created_status ON orders (created_at, status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_user_created ON orders (user_id, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_journal_entries_ref ON journal_entries (ref_type, ref_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_journal_entries_created ON journal_entries (created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_journal_lines_entry ON journal_lines (entry_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cashier_cycles_user_status ON cashier_cycles (user_id, status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cashier_cycles_opened ON cashier_cycles (opened_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (expense_date)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_category_date ON expenses (category_id, expense_date)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_treasury_created ON treasury_transactions (created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_treasury_account_created ON treasury_transactions (account_code, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_suppliers_active_name ON suppliers (is_active, name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchase_invoices_supplier_date ON purchase_invoices (supplier_id, invoice_date)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchase_invoices_status ON purchase_invoices (status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchase_items_invoice ON purchase_items (purchase_invoice_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_supplier_payments_supplier_date ON supplier_payments (supplier_id, payment_date)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items (order_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items (product_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_maintenance_status_created ON maintenance_tickets (status, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_maintenance_technician_status ON maintenance_tickets (technician_id, status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_maintenance_history_ticket_created ON maintenance_ticket_history (ticket_id, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_maintenance_templates_active_sort ON maintenance_issue_templates (is_active, sort_order)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_used_devices_created ON used_device_purchases (created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_customers_active_name ON customers (is_active, name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_suspended_orders_status_created ON suspended_orders (status, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_suspended_orders_number ON suspended_orders (suspend_number)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_customer_due_payments_order_created ON customer_due_payments (order_id, created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_created ON audit_log (created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log (user_name)");

        // Seed default chart of accounts (minimal)
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('1010', 'الخزنة الرئيسية', 'asset', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('1020', 'البنك / المحافظ', 'asset', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('1040', 'ذمم العملاء', 'asset', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('2010', 'ضريبة القيمة المضافة - مخرجات', 'liability', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('4010', 'إيراد المبيعات', 'revenue', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('5010', 'مردودات المبيعات', 'expense', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('1030', 'المخزون', 'asset', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('2020', 'ذمم الموردين', 'liability', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('6010', 'مصروفات تشغيلية', 'expense', 1)");
        $this->query("INSERT OR IGNORE INTO accounts (code, name, type, is_system) VALUES ('3010', 'تسويات الخزنة', 'equity', 1)");

        // Seed expense categories
        $this->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES ('إيجار', 1)");
        $this->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES ('كهرباء ومياه', 1)");
        $this->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES ('رواتب', 1)");
        $this->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES ('صيانة وتشغيل', 1)");
        $this->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES ('نثريات', 1)");

        // Seed policies for existing cashiers/admins
        $users = $this->fetchAll("SELECT id, role FROM users WHERE is_active = 1");
        foreach ($users as $u) {
            $requireReview = ($u['role'] === 'cashier') ? 1 : 0;
            $this->query(
                "INSERT OR IGNORE INTO user_finance_policies (user_id, require_manager_review, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
                [$u['id'], $requireReview]
            );
        }
        $this->backfillPinHashes();
    }

    private function backfillPinHashes() {
        // Migrate old plain PINs to hashed PINs without breaking existing users.
        $rows = $this->fetchAll(
            "SELECT id, pin, pin_hash FROM users WHERE pin IS NOT NULL AND pin <> '' AND (pin_hash IS NULL OR pin_hash = '')"
        );
        foreach ($rows as $r) {
            $pin = (string)$r['pin'];
            if ($pin === '') continue;
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            $this->query("UPDATE users SET pin_hash = ?, pin = NULL WHERE id = ?", [$pinHash, $r['id']]);
        }
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
