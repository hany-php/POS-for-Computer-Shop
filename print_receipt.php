<?php
require_once __DIR__ . '/includes/bootstrap.php';
// Auth check optional if we just render data passed to us, but safer to require login
Auth::requireLogin();
$settings = getStoreSettings();
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة الفاتورة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; font-family: 'Cairo', sans-serif; background: #fff; }
        #receipt { width: 80mm; margin: 0 auto; padding: 5mm; background: #fff; }
        @media print {
            body { background: #fff; }
            #receipt { width: 100%; padding: 0; }
            .no-print { display: none; }
        }
        .btn {
            background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-family: inherit; font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; padding: 10px; background: #f0f0f0; border-bottom: 1px solid #ccc;">
        <button onclick="window.print(); setTimeout(() => window.close(), 500);" class="btn">طباعة</button>
        <button onclick="window.close()" class="btn" style="background: #64748b; margin-right: 10px;">إغلاق</button>
    </div>

    <div id="receipt"></div>

    <script>
        // Retrieve data from localStorage
        try {
            const data = JSON.parse(localStorage.getItem('pos_print_data'));
            if (data) {
                document.getElementById('receipt').innerHTML = data.html;
                
                // Auto resize window to fit content
                setTimeout(() => {
                    const contentHeight = document.body.scrollHeight;
                    // Add some buffer for window chrome (title bar etc)
                    const chromeHeight = window.outerHeight - window.innerHeight;
                    if (chromeHeight > 0) {
                        window.resizeTo(450, contentHeight + chromeHeight + 50);
                    } else {
                        window.resizeTo(450, contentHeight + 80);
                    }
                }, 100);
            } else {
                document.getElementById('receipt').innerHTML = '<p style="text-align:center;color:red">لا توجد بيانات للطباعة</p>';
            }
        } catch (e) {
            document.getElementById('receipt').innerHTML = '<p style="text-align:center;color:red">حدث خطأ في قراءة البيانات</p>';
        }
    </script>
</body>
</html>
