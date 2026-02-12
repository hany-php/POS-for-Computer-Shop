-- POS System Database Schema
-- نظام نقاط البيع - هيكل قاعدة البيانات
-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    pin TEXT,
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
-- Insert default settings
INSERT
    OR IGNORE INTO settings (key, value)
VALUES ('tax_rate', '0.15');
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