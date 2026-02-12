-- Seed Data for POS System (EGP - Egyptian Pound)
-- بيانات تجريبية لنظام نقاط البيع
-- Default Admin User (password: admin123)
INSERT
    OR IGNORE INTO users (username, password, pin, full_name, role)
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '1234',
        'أحمد المدير',
        'admin'
    );
-- Default Cashier (pin: 1111)
INSERT
    OR IGNORE INTO users (username, password, pin, full_name, role)
VALUES (
        'cashier1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '1111',
        'محمد الكاشير',
        'cashier'
    );
-- Default Technician
INSERT
    OR IGNORE INTO users (username, password, pin, full_name, role)
VALUES (
        'tech1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '2222',
        'خالد الفني',
        'technician'
    );
-- Categories
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (1, 'أجهزة كمبيوتر', 'computer', 1);
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (2, 'لابتوبات', 'laptop', 2);
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (3, 'إكسسوارات', 'mouse', 3);
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (4, 'شبكات', 'router', 4);
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (5, 'كاميرات مراقبة', 'videocam', 5);
INSERT
    OR IGNORE INTO categories (id, name, icon, sort_order)
VALUES (6, 'خدمات الصيانة', 'build', 6);
-- Sample products
INSERT
    OR IGNORE INTO products (
        id,
        name,
        description,
        category_id,
        price,
        cost_price,
        quantity,
        barcode,
        image_url
    )
VALUES (
        1,
        'جهاز مكتبي ديل أوبتيبليكس',
        'Core i5, 8GB RAM, 256GB SSD',
        1,
        2100,
        1800,
        15,
        '1001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuAaVlssl1Vr2KkZi87dAOq9iNvIsZ8n9UqMLMVHM8vqanySUQDhQdzZBk6ZNsOMwiLS1JJ-W0r_jYd4VvN98AGP-H95DNlF0tQj2Fhfid1lymD_GN1-maUAapvNioRwlJDCuwYKxPvwMKIIea1oQdUJicPVHfQkWuHJ-LU9_HhGbSpbWCFa30-euqiLGj1ctw8NmUnCfCMfLVabXfnZepJAtNvC-zf0fFNWb7WYTL_en0XSBAnm0W18IFdcbLsjWr5OPUuPZ3BbdSc'
    ),
    (
        2,
        'كمبيوتر ألعاب مخصص',
        'RTX 4070, AMD Ryzen 7, 32GB RAM',
        1,
        6500,
        5500,
        8,
        '1001002',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuAaVlssl1Vr2KkZi87dAOq9iNvIsZ8n9UqMLMVHM8vqanySUQDhQdzZBk6ZNsOMwiLS1JJ-W0r_jYd4VvN98AGP-H95DNlF0tQj2Fhfid1lymD_GN1-maUAapvNioRwlJDCuwYKxPvwMKIIea1oQdUJicPVHfQkWuHJ-LU9_HhGbSpbWCFa30-euqiLGj1ctw8NmUnCfCMfLVabXfnZepJAtNvC-zf0fFNWb7WYTL_en0XSBAnm0W18IFdcbLsjWr5OPUuPZ3BbdSc'
    ),
    (
        3,
        'ماك بوك برو M2',
        'ذاكرة 16 جيجا، 512 SSD',
        2,
        5499,
        4800,
        10,
        '2001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBPWVmsiaI_gxDQFxJiu7ye4GF0hWctwduL6Yxv0aIjUjhuCryaXOY7e2U10vdJ2ESY1z7ejbjI255W1y6t6Nuv7gaQ6uJJL9yXRzinkaJHxQ748t4ipNhLJ7sSW26k9wFVUBUkRYxYvxoXTtcaVLndxVCnd_iJ7mlDBruXQqWK0WcFxpeMp6zC93_cEU0rOQ1CfawaKKHs8yV4FsBLl-lIREfGXBtRnRBFJJOUJ2NJfIHVsb1J3KquZwhXtoQHDHItuu3QAa3nN2k'
    ),
    (
        4,
        'لابتوب ديل XPS 15',
        'Core i7, 16GB RAM, 512GB SSD',
        2,
        4200,
        3600,
        12,
        '2001002',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuDgJkyTmql9aXcCSsLWkpBpM4Dxwo-HkjmzefhCR2T1dQNfoD-yaUYDTioIx7-E_cF5qOxsGrqUyDNChWevKOGaoNDHzen0PFfa_H7K8ouHO3r4thr3C5sQ6V2CPudpIKgjzBRTznFTEIVkmVqZ9xswI8JJ9V8YywSeUxiaiP-taiya3-onhDQlDEsqhLzveCIn1mBdP05wWxwRiZxp3s-VLnUuJTZAGV3TRbyZnDuFNoHL8dH2Q0Ww8wDmvdLLyQXVSc7t4arLKG8'
    ),
    (
        5,
        'لابتوب لينوفو ThinkPad',
        'Core i5, 8GB RAM, 256GB SSD',
        2,
        3200,
        2700,
        6,
        '2001003',
        NULL
    ),
    (
        6,
        'ماوس لوجيتك لاسلكي MX Master 3S',
        'Bluetooth, USB-C',
        3,
        450,
        350,
        25,
        '3001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuCPVt9A3MFzjvcXMiTpQcYLo4A8LTJLoouDCWNJXfjquDUML6ZSn5JvY1yjXRBgP_PpcKO6n4YhiaTck4ADxaUub_4PbIwaEZ4dFECV7hjSfSIC3gSK7Fl_F7IUid2Sqc2XGdpPBqyytJm4FfbCJmGuDO40MTrl6mM9u9D-6l9-Q-YtREhPnItL3_6xihrCx7s7rGGtgRibuxksF0dEybm6BMPmJ3MR65yMV2E8EU43pErsCkP3NXplqSxSBG1gOF9yv1hNz0mL3VE'
    ),
    (
        7,
        'كيبورد ميكانيكي RGB للألعاب',
        'Blue Switch, Arabic Layout',
        3,
        299,
        200,
        3,
        '3001002',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuD0vRJW2ih_bPwfOZus4HRCUC5T2qLsONvCSL6SKSzwRPjeA7Rt0b3XMcIDWBOZ8_wCBZ2taUAKWMxpqvpKBzIhnZ6UykRiAmW3lyaUho0fU_s5ruILNFWC8uKeqWO4A1aH6mLG-QBb7zEzd66HO6-I0FdItXyNyWUkO83eHSCdmp5lXT5PcnlcVdIWeQSKq0SJJNv6f07CZ5IrPfLOp5Qar5yjjiLbA--ZozQHWjcGx8k4xq4Z5LuetBdmdp8eYC7QIYJCGKDYZS8'
    ),
    (
        8,
        'سماعات رأس لاسلكية',
        'Noise Cancelling, Bluetooth 5.0',
        3,
        280,
        180,
        20,
        '3001003',
        NULL
    ),
    (
        9,
        'سويتش شبكة تي بي لينك 8 منافذ',
        'Gigabit, Metal Case',
        4,
        120,
        85,
        30,
        '4001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuAbjQOP-ctIPIPEs4WLTekRL2PW6rwOKeBznG1CaqfYGd6rGDVM07QTC2QMqooruTj-g5Rr3fokev22zYoJEhq09B9E2DVQ3r1mSY9uNOh4fpVYmH6V33qQo0ot82xLRr9nse-F8LzI1YCdxgoknmIDN7-zGN5wYty19rdB__3pUVegA2m6zWxGXT701dVpre6rl0gK-jxYT9xFl1iTb0hQfUwrk3lou-17pkAu-Pa_37MeO-h3Qrb4xFYWzFPXqTzEI9-7-NtHMVY'
    ),
    (
        10,
        'راوتر واي فاي 6',
        'Dual Band, 5GHz',
        4,
        350,
        250,
        18,
        '4001002',
        NULL
    ),
    (
        11,
        'كيبل شبكة Cat6 - 30 متر',
        'UTP, Blue',
        4,
        45,
        25,
        50,
        '4001003',
        NULL
    ),
    (
        12,
        'كاميرا مراقبة خارجية واي فاي',
        '2MP, Night Vision, IP66',
        5,
        350,
        250,
        15,
        '5001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuDkfgQ1TjVdTWMDgXIf5jketqC9nCYtzDvlZH15uUAGVSqyPuKd6z-WdSHiHXYmMGgPf3WS5svI0jWLJsp10_tWELh5mT9iMiTbEQdL50tXKqXxPCTvKgLB5147p-i88WMcx2-7ubgE4ho7JQ3zwfbmxmQ85prZXhfqpSnDMeNx8sK9SsNhyVE5XtDjjUg9oI4yUhH8CzP8cyq_C6WUd7zecBJNBTJ3JUL8k90xmPbq-RZWXXggxOJ8bwdgC83TdV_oqXQ1uOvB3Pg'
    ),
    (
        13,
        'DVR 8 قنوات',
        'H.265, 4MP Recording',
        5,
        650,
        450,
        10,
        '5001002',
        NULL
    ),
    (
        14,
        'نظام كاميرات مراقبة 4 كاميرات',
        'Full HD, DVR + 4 Cameras + HDD',
        5,
        1800,
        1300,
        5,
        '5001003',
        NULL
    ),
    (
        15,
        'معالج إنتل كور i7 الجيل 12',
        'LGA 1700, 12 Cores',
        6,
        1650,
        1400,
        7,
        '6001001',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBPOTMmure8wQANh-DIDuidlXyHFvT8Bui2Y4O_A17L_WcaEpoGUPNezp3aZ0p0U_mXLKkLqdv5zs4HySKfYKD11vGmu1_IMjBdbR4uTgnfAx7VF08pGaKWXlZbMSkYDfWPj_0HcuJaV6WIXjtif9qNOUmuVgubUGPVMbdKhzMi64zfliZm_3QL3AxpedUd1qrj8uEyppFqdBIVZ2tdDyoTCtG1WaR2SJJf_NGGRF1CuDgSgwpZanMpiyyr4tzN5IeGKsxJ29CqwV4'
    ),
    (
        16,
        'كرت شاشة RTX 3060 Ti',
        '8GB GDDR6X',
        6,
        1899,
        1600,
        4,
        '6001002',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBUEhpPQPsC8xwhsTH4lgVgn_bxTWr6P1aB5YxVt1_UbH6ohTOPMiRTI-u559RFpBhT8GipPdpL9QMIZMMLuOWmf_wgFsFXCbeKIOFuechrSQo8iN_xSYpwgQMTweeLSSHeNTWNRUGUO0i0Ksq_l30FD9sKhduzmFVMD_sFXJ2tA8tARUYeRUH6usxRQXTzjpVVogxVyHUpQQ_z4ecGzchd2w2_n_XLVzSLwX6Gz4nkdLyQNWWtj_3ovwrQofQJr3zyAkL47KYrRls'
    ),
    (
        17,
        'خدمة فحص وتشخيص أجهزة',
        'فحص شامل للجهاز وتحديد الأعطال',
        6,
        50,
        0,
        999,
        '6001003',
        NULL
    ),
    (
        18,
        'خدمة تنظيف وصيانة داخلية',
        'تنظيف وتغيير المعجون الحراري',
        6,
        100,
        0,
        999,
        '6001004',
        NULL
    );