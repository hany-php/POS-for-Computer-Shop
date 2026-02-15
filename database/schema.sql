-- POS System Database Schema
-- نظام نقاط البيع - هيكل قاعدة البيانات
-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    pin TEXT,
    pin_hash TEXT,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin', 'cashier', 'technician')),
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    icon TEXT DEFAULT 'category',
    sort_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1
);
-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    category_id INTEGER,
    price REAL NOT NULL DEFAULT 0,
    cost_price REAL DEFAULT 0,
    quantity INTEGER DEFAULT 0,
    barcode TEXT,
    serial_number TEXT,
    image_url TEXT,
    is_used INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number TEXT UNIQUE NOT NULL,
    user_id INTEGER,
    subtotal REAL DEFAULT 0,
    tax_amount REAL DEFAULT 0,
    discount_amount REAL DEFAULT 0,
    total REAL DEFAULT 0,
    payment_method TEXT DEFAULT 'cash',
    payment_received REAL DEFAULT 0,
    change_amount REAL DEFAULT 0,
    status TEXT DEFAULT 'completed' CHECK(status IN ('completed', 'cancelled', 'refunded')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER,
    product_name TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price REAL NOT NULL,
    total_price REAL NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Accounting accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('asset', 'liability', 'equity', 'revenue', 'expense')),
    is_active INTEGER DEFAULT 1,
    is_system INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Journal entries and lines
CREATE TABLE IF NOT EXISTS journal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_number TEXT UNIQUE NOT NULL,
    ref_type TEXT,
    ref_id INTEGER,
    ref_number TEXT,
    description TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS journal_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    account_code TEXT NOT NULL,
    debit REAL DEFAULT 0,
    credit REAL DEFAULT 0,
    line_note TEXT,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
);

-- User finance policy (cashier close review)
CREATE TABLE IF NOT EXISTS user_finance_policies (
    user_id INTEGER PRIMARY KEY,
    require_manager_review INTEGER DEFAULT 1,
    updated_by INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Cashier cycles
CREATE TABLE IF NOT EXISTS cashier_cycles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    status TEXT DEFAULT 'open' CHECK(status IN ('open', 'pending_review', 'closed')),
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
);

-- Expense categories
CREATE TABLE IF NOT EXISTS expense_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Expenses
CREATE TABLE IF NOT EXISTS expenses (
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
);

-- Treasury transactions
CREATE TABLE IF NOT EXISTS treasury_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    txn_type TEXT NOT NULL CHECK(txn_type IN ('in', 'out')),
    account_code TEXT NOT NULL,
    amount REAL NOT NULL DEFAULT 0,
    source TEXT,
    payment_method TEXT DEFAULT 'cash',
    ref_type TEXT,
    ref_id INTEGER,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
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
);

-- Purchase invoices
CREATE TABLE IF NOT EXISTS purchase_invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT UNIQUE NOT NULL,
    supplier_id INTEGER NOT NULL,
    invoice_date DATE NOT NULL,
    total_amount REAL DEFAULT 0,
    paid_amount REAL DEFAULT 0,
    due_amount REAL DEFAULT 0,
    payment_method TEXT DEFAULT 'cash',
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'partial', 'paid')),
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Purchase items
CREATE TABLE IF NOT EXISTS purchase_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_invoice_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_cost REAL NOT NULL DEFAULT 0,
    total_cost REAL NOT NULL DEFAULT 0,
    FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Supplier payments
CREATE TABLE IF NOT EXISTS supplier_payments (
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
);
-- Maintenance Tickets table
CREATE TABLE IF NOT EXISTS maintenance_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_number TEXT UNIQUE NOT NULL,
    customer_name TEXT NOT NULL,
    customer_phone TEXT NOT NULL,
    device_type TEXT NOT NULL,
    device_brand TEXT,
    device_model TEXT,
    serial_number TEXT,
    problem_description TEXT NOT NULL,
    estimated_cost REAL DEFAULT 0,
    actual_cost REAL DEFAULT 0,
    discount REAL DEFAULT 0,
    status TEXT DEFAULT 'pending_inspection' CHECK(
        status IN (
            'pending_inspection',
            'under_maintenance',
            'ready_for_pickup',
            'delivered',
            'cancelled'
        )
    ),
    technician_id INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (technician_id) REFERENCES users(id)
);

-- Maintenance Issue Templates
CREATE TABLE IF NOT EXISTS maintenance_issue_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    device_type TEXT DEFAULT '',
    problem_text TEXT NOT NULL,
    default_estimated_cost REAL DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Maintenance ticket timeline
CREATE TABLE IF NOT EXISTS maintenance_ticket_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    action_type TEXT NOT NULL,
    action_label TEXT NOT NULL,
    notes TEXT,
    changed_by INTEGER,
    changed_by_name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES maintenance_tickets(id) ON DELETE CASCADE
);
-- Used Device Purchases table
CREATE TABLE IF NOT EXISTS used_device_purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_number TEXT UNIQUE NOT NULL,
    seller_name TEXT NOT NULL,
    seller_phone TEXT,
    seller_id_number TEXT,
    device_type TEXT NOT NULL,
    device_brand TEXT,
    device_model TEXT,
    serial_number TEXT,
    device_condition TEXT DEFAULT 'good' CHECK(
        device_condition IN (
            'excellent',
            'very_good',
            'good',
            'acceptable',
            'damaged'
        )
    ),
    condition_notes TEXT,
    purchase_price REAL NOT NULL,
    added_to_inventory INTEGER DEFAULT 1,
    product_id INTEGER,
    user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Settings table
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);

-- Customer due payments (collection of outstanding invoice balances)
CREATE TABLE IF NOT EXISTS customer_due_payments (
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
);

-- Suspended (saved) POS orders
CREATE TABLE IF NOT EXISTS suspended_orders (
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
);
-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_products_active_category_qty ON products (is_active, category_id, quantity);
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products (barcode);
CREATE INDEX IF NOT EXISTS idx_orders_created_status ON orders (created_at, status);
CREATE INDEX IF NOT EXISTS idx_orders_user_created ON orders (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_journal_entries_ref ON journal_entries (ref_type, ref_id);
CREATE INDEX IF NOT EXISTS idx_journal_entries_created ON journal_entries (created_at);
CREATE INDEX IF NOT EXISTS idx_journal_lines_entry ON journal_lines (entry_id);
CREATE INDEX IF NOT EXISTS idx_cashier_cycles_user_status ON cashier_cycles (user_id, status);
CREATE INDEX IF NOT EXISTS idx_cashier_cycles_opened ON cashier_cycles (opened_at);
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (expense_date);
CREATE INDEX IF NOT EXISTS idx_expenses_category_date ON expenses (category_id, expense_date);
CREATE INDEX IF NOT EXISTS idx_treasury_created ON treasury_transactions (created_at);
CREATE INDEX IF NOT EXISTS idx_treasury_account_created ON treasury_transactions (account_code, created_at);
CREATE INDEX IF NOT EXISTS idx_suppliers_active_name ON suppliers (is_active, name);
CREATE INDEX IF NOT EXISTS idx_purchase_invoices_supplier_date ON purchase_invoices (supplier_id, invoice_date);
CREATE INDEX IF NOT EXISTS idx_purchase_invoices_status ON purchase_invoices (status);
CREATE INDEX IF NOT EXISTS idx_purchase_items_invoice ON purchase_items (purchase_invoice_id);
CREATE INDEX IF NOT EXISTS idx_supplier_payments_supplier_date ON supplier_payments (supplier_id, payment_date);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items (order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items (product_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_status_created ON maintenance_tickets (status, created_at);
CREATE INDEX IF NOT EXISTS idx_maintenance_technician_status ON maintenance_tickets (technician_id, status);
CREATE INDEX IF NOT EXISTS idx_maintenance_history_ticket_created ON maintenance_ticket_history (ticket_id, created_at);
CREATE INDEX IF NOT EXISTS idx_maintenance_templates_active_sort ON maintenance_issue_templates (is_active, sort_order);
CREATE INDEX IF NOT EXISTS idx_used_devices_created ON used_device_purchases (created_at);
CREATE INDEX IF NOT EXISTS idx_customers_active_name ON customers (is_active, name);
CREATE INDEX IF NOT EXISTS idx_suspended_orders_status_created ON suspended_orders (status, created_at);
CREATE INDEX IF NOT EXISTS idx_suspended_orders_number ON suspended_orders (suspend_number);
CREATE INDEX IF NOT EXISTS idx_customer_due_payments_order_created ON customer_due_payments (order_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_created ON audit_log (created_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log (user_name);
-- Insert default settings
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('tax_rate', '0.15');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('tax_enabled', '1');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('hijri_date_enabled', '1');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('store_name', 'تك ستور للإلكترونيات');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('store_address', 'شارع الملك فهد، الرياض');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('store_phone', '920000000');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('currency', 'ر.س');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('low_stock_threshold', '5');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('backup_auto_enabled', '1');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('backup_frequency_hours', '24');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('backup_keep_count', '20');
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('last_auto_backup_at', '');

INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('1010', 'الخزنة الرئيسية', 'asset', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('1020', 'البنك / المحافظ', 'asset', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('1040', 'ذمم العملاء', 'asset', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('2010', 'ضريبة القيمة المضافة - مخرجات', 'liability', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('4010', 'إيراد المبيعات', 'revenue', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('5010', 'مردودات المبيعات', 'expense', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('1030', 'المخزون', 'asset', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('2020', 'ذمم الموردين', 'liability', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('6010', 'مصروفات تشغيلية', 'expense', 1);
INSERT
    OR IGNORE INTO accounts (code, name, type, is_system)
VALUES ('3010', 'تسويات الخزنة', 'equity', 1);

INSERT
    OR IGNORE INTO expense_categories (name, is_active)
VALUES ('إيجار', 1);
INSERT
    OR IGNORE INTO expense_categories (name, is_active)
VALUES ('كهرباء ومياه', 1);
INSERT
    OR IGNORE INTO expense_categories (name, is_active)
VALUES ('رواتب', 1);
INSERT
    OR IGNORE INTO expense_categories (name, is_active)
VALUES ('صيانة وتشغيل', 1);
INSERT
    OR IGNORE INTO expense_categories (name, is_active)
VALUES ('نثريات', 1);

INSERT
    OR IGNORE INTO maintenance_issue_templates (id, title, device_type, problem_text, default_estimated_cost, sort_order, is_active)
VALUES (1, 'تنظيف داخلي وتغيير معجون', 'لابتوب', 'سخونة عالية مع صوت مروحة مرتفع ويحتاج تنظيف داخلي وتغيير معجون حراري.', 120, 1, 1);
INSERT
    OR IGNORE INTO maintenance_issue_templates (id, title, device_type, problem_text, default_estimated_cost, sort_order, is_active)
VALUES (2, 'فحص باور سبلاي', 'كمبيوتر مكتبي', 'الجهاز لا يعمل ويحتاج فحص مزود الطاقة وخطوط الكهرباء الداخلية.', 80, 2, 1);
INSERT
    OR IGNORE INTO maintenance_issue_templates (id, title, device_type, problem_text, default_estimated_cost, sort_order, is_active)
VALUES (3, 'تغيير هارد إلى SSD', 'لابتوب', 'بطء شديد في الإقلاع ويُوصى بتركيب SSD ونقل النظام والبيانات.', 250, 3, 1);
INSERT
    OR IGNORE INTO maintenance_issue_templates (id, title, device_type, problem_text, default_estimated_cost, sort_order, is_active)
VALUES (4, 'مشكلة شبكة واتصال', 'جهاز شبكة', 'انقطاع متكرر في الشبكة ويحتاج فحص الإعدادات والكابلات والتحديث.', 90, 4, 1);
INSERT
    OR IGNORE INTO maintenance_issue_templates (id, title, device_type, problem_text, default_estimated_cost, sort_order, is_active)
VALUES (5, 'فحص DVR/NVR والكاميرات', 'DVR/NVR', 'صورة متقطعة أو عدم تسجيل ويحتاج فحص القنوات والتخزين والطاقة.', 150, 5, 1);
