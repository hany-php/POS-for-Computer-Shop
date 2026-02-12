<?php
require_once __DIR__ . '/includes/bootstrap.php';
$settings = getStoreSettings();

// Handle login POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF Token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('خطأ في التحقق من صحة الطلب (CSRF)');
    }

    $auth = new Auth();
    
    if (isset($_POST['login_type']) && $_POST['login_type'] === 'admin') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if ($auth->loginWithPassword($username, $password)) {
            header('Location: pos.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    } else {
        $pin = $_POST['pin'] ?? '';
        if ($auth->loginWithPin($pin)) {
            header('Location: pos.php');
            exit;
        } else {
            $error = 'الرمز السري غير صحيح';
        }
    }
}

if (Auth::isLoggedIn()) {
    header('Location: pos.php');
    exit;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?= sanitize($settings['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "primary-dark": "#0f66bd",
                        "background-light": "#f6f7f8",
                        "surface-light": "#ffffff",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "arabic": ["Cairo", "sans-serif"],
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Cairo', 'Space Grotesk', sans-serif; }
        .font-numeric { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="bg-background-light min-h-screen flex items-center justify-center p-4">

<main class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
    <!-- Left Side: Branding -->
    <div class="hidden lg:flex flex-col justify-center items-start space-y-8 p-8">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <?php if (!empty($settings['store_logo_url'])): ?>
                <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/10 overflow-hidden p-2">
                    <img src="<?= sanitize($settings['store_logo_url']) ?>" alt="Logo" class="w-full h-full object-contain">
                </div>
                <?php else: ?>
                <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/30">
                    <span class="material-icons-round text-white text-3xl">memory</span>
                </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-slate-800 tracking-wide"><?= sanitize($settings['store_name']) ?></h1>
            </div>
            <p class="text-slate-500 text-lg leading-relaxed max-w-md">
                نظام متكامل لإدارة المبيعات والصيانة. الحل الأمثل لمحلات الكمبيوتر والأنظمة الأمنية.
            </p>
        </div>
        <div class="w-full h-80 rounded-2xl overflow-hidden relative shadow-2xl shadow-primary/10 border border-slate-200 group bg-gradient-to-br from-primary/20 to-primary/5">
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent z-10 flex items-end p-6">
                <div class="text-white z-20">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="bg-green-500 w-2 h-2 rounded-full animate-pulse"></span>
                        <span class="text-sm font-medium opacity-90">النظام يعمل بكفاءة</span>
                    </div>
                    <p class="text-sm opacity-80 font-numeric">v<?= APP_VERSION ?> (Stable)</p>
                </div>
            </div>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="material-icons-round text-primary/20 text-[180px]">computer</span>
            </div>
        </div>
    </div>

    <!-- Right Side: Login Card -->
    <div class="w-full max-w-md mx-auto">
        <div class="bg-surface-light rounded-2xl shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100 relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-8 -mt-8 pointer-events-none"></div>
            
            <div class="p-8 pb-6">
                <!-- Mobile Logo -->
                <!-- Mobile Logo -->
                <div class="lg:hidden flex justify-center mb-6">
                    <?php if (!empty($settings['store_logo_url'])): ?>
                    <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shadow-md overflow-hidden p-2">
                        <img src="<?= sanitize($settings['store_logo_url']) ?>" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <?php else: ?>
                    <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center shadow-md">
                        <span class="material-icons-round text-white text-2xl">memory</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PIN Login Form (Default) -->
                <div id="pin-login">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">تسجيل الدخول</h2>
                        <p class="text-slate-500">الرجاء إدخال الرمز السري للموظف</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm text-center">
                        <?= $error ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="pin-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="login_type" value="pin">
                        <input type="hidden" name="pin" id="pin-input" value="">
                        
                        <!-- PIN Display -->
                        <div class="mb-8">
                            <div class="bg-slate-50 border-2 border-primary/30 focus-within:border-primary rounded-xl h-16 flex items-center justify-center transition-all duration-300 shadow-inner">
                                <div class="flex gap-4" dir="ltr" id="pin-dots">
                                    <div class="w-4 h-4 rounded-full bg-slate-300 transition-colors" id="dot1"></div>
                                    <div class="w-4 h-4 rounded-full bg-slate-300 transition-colors" id="dot2"></div>
                                    <div class="w-4 h-4 rounded-full bg-slate-300 transition-colors" id="dot3"></div>
                                    <div class="w-4 h-4 rounded-full bg-slate-300 transition-colors" id="dot4"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Keypad -->
                        <div class="grid grid-cols-3 gap-4 mb-8 font-numeric" dir="ltr">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                            <button type="button" onclick="addPin(<?= $i ?>)" class="h-16 rounded-xl bg-slate-100 hover:bg-slate-200 text-2xl font-semibold text-slate-700 transition-colors shadow-sm active:scale-95">
                                <?= $i ?>
                            </button>
                            <?php endfor; ?>
                            <button type="button" onclick="clearPin()" class="h-16 rounded-xl bg-red-50 hover:bg-red-100 text-red-500 transition-colors shadow-sm active:scale-95 flex items-center justify-center">
                                <span class="material-icons-round text-2xl">backspace</span>
                            </button>
                            <button type="button" onclick="addPin(0)" class="h-16 rounded-xl bg-slate-100 hover:bg-slate-200 text-2xl font-semibold text-slate-700 transition-colors shadow-sm active:scale-95">0</button>
                            <button type="submit" class="h-16 rounded-xl bg-primary hover:bg-primary-dark text-white transition-colors shadow-lg shadow-primary/30 active:scale-95 flex items-center justify-center">
                                <span class="material-icons-round text-3xl transform rotate-180">login</span>
                            </button>
                        </div>
                    </form>

                    <!-- Admin Link -->
                    <div class="flex justify-center pt-2 border-t border-slate-100">
                        <button onclick="showAdminLogin()" class="group flex items-center gap-2 px-4 py-2 rounded-lg text-slate-500 hover:text-primary transition-colors">
                            <span class="material-icons-round text-sm group-hover:scale-110 transition-transform">admin_panel_settings</span>
                            <span class="text-sm font-medium">دخول المدير</span>
                        </button>
                    </div>
                </div>

                <!-- Admin Login Form (Hidden) -->
                <div id="admin-login" class="hidden">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">دخول المدير</h2>
                        <p class="text-slate-500">أدخل اسم المستخدم وكلمة المرور</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm text-center">
                        <?= $error ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">اسم المستخدم</label>
                                <div class="relative">
                                    <span class="material-icons-round absolute right-3 top-3 text-slate-400 text-xl">person</span>
                                    <input type="text" name="username" required
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pr-12 pl-4 text-slate-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                        placeholder="admin">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">كلمة المرور</label>
                                <div class="relative">
                                    <span class="material-icons-round absolute right-3 top-3 text-slate-400 text-xl">lock</span>
                                    <input type="password" name="password" required
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pr-12 pl-4 text-slate-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                        placeholder="••••••••">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all flex items-center justify-center gap-2">
                            <span>تسجيل الدخول</span>
                            <span class="material-icons-round transform rotate-180">login</span>
                        </button>
                    </form>

                    <div class="flex justify-center pt-4 mt-4 border-t border-slate-100">
                        <button onclick="showPinLogin()" class="group flex items-center gap-2 px-4 py-2 rounded-lg text-slate-500 hover:text-primary transition-colors">
                            <span class="material-icons-round text-sm group-hover:scale-110 transition-transform">dialpad</span>
                            <span class="text-sm font-medium">دخول بالرمز السري</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Status -->
            <div class="bg-slate-50 p-3 flex justify-between items-center text-xs text-slate-400 border-t border-slate-100">
                <span class="font-numeric">ID: Term-01</span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    متصل بالخادم
                </span>
            </div>
        </div>
        <div class="mt-8 text-center">
            <p class="text-slate-400 text-sm">جميع الحقوق محفوظة © <?= date('Y') ?> <?= sanitize($settings['store_name']) ?></p>
        </div>
    </div>
</main>

<script>
let pin = '';
const maxDigits = 4;

function addPin(digit) {
    if (pin.length < maxDigits) {
        pin += digit;
        updateDots();
    }
}

function clearPin() {
    if (pin.length > 0) {
        pin = pin.slice(0, -1);
        updateDots();
    }
}

function updateDots() {
    for (let i = 1; i <= maxDigits; i++) {
        const dot = document.getElementById('dot' + i);
        if (i <= pin.length) {
            dot.classList.remove('bg-slate-300');
            dot.classList.add('bg-slate-800', 'animate-pulse');
        } else {
            dot.classList.add('bg-slate-300');
            dot.classList.remove('bg-slate-800', 'animate-pulse');
        }
    }
    document.getElementById('pin-input').value = pin;
}

function showAdminLogin() {
    document.getElementById('pin-login').classList.add('hidden');
    document.getElementById('admin-login').classList.remove('hidden');
}

function showPinLogin() {
    document.getElementById('admin-login').classList.add('hidden');
    document.getElementById('pin-login').classList.remove('hidden');
}

// Keyboard support
document.addEventListener('keydown', (e) => {
    if (document.getElementById('admin-login').classList.contains('hidden')) {
        if (e.key >= '0' && e.key <= '9') addPin(parseInt(e.key));
        if (e.key === 'Backspace') clearPin();
        if (e.key === 'Enter') document.getElementById('pin-form').submit();
    }
});
</script>
</body>
</html>
