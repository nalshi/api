// --- محرك التنبيهات الذكي (مُحسّن للعمل في الخلفية) ---
const orderAudio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

// تسجيل Service Worker لضمان بقاء التطبيق فعالاً في الخلفية
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js').then(function(registration) {
            console.log('تم تفعيل ServiceWorker بنجاح للنطاق:', registration.scope);
        }, function(err) {
            console.log('فشل تفعيل ServiceWorker:', err);
        });
    });
}

// دالة التعامل مع زر التفعيل في الإعدادات
async function handleNotificationToggle(checkbox) {
    if (checkbox.checked) {
        // طلب الصلاحية من المتصفح/الهاتف
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            showT('يجب السماح بالإشعارات من إعدادات المتصفح/الهاتف أولاً', 'error');
            checkbox.checked = false;
            return;
        }
        
        // تجربة الصوت للتأكد من عمله
        orderAudio.play().catch(() => console.log("تحذير: الصوت يتطلب نقرة أولى من المستخدم"));
        showT('تم تفعيل التنبيهات اللحظية (SMS / إشعارات) ✅', 'success');
    }
    saveSpecificSetting('notifications');
}

// دالة إرسال إشعار النظام (للهاتف والكمبيوتر)
function sendOrderSystemNotification(orderId) {
    // تشغيل الصوت دائماً
    orderAudio.play().catch(() => {});
    
    // إرسال الإشعار المنبثق
    if (Notification.permission === "granted") {
        // إذا كان Service Worker مدعوماً (أفضل للهواتف)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function(registration) {
                registration.showNotification("🛍️ طلب جديد بانتظار التجهيز!", {
                    body: `لديك طلب جديد يحتاج موافقتك. رقم الطلب: #${orderId.substring(0,8)}`,
                    icon: 'https://cdn-icons-png.flaticon.com/512/3500/3500833.png',
                    vibrate: [200, 100, 200, 100, 200, 100, 200],
                    requireInteraction: true, // يبقى الإشعار حتى ينقر عليه التاجر
                    badge: 'https://cdn-icons-png.flaticon.com/512/3500/3500833.png'
                });
            });
        } else {
            // الطريقة التقليدية (للكمبيوتر)
            const notification = new Notification("🛍️ طلب جديد بانتظار التجهيز!", {
                body: `لديك طلب جديد يحتاج موافقتك. رقم الطلب: #${orderId.substring(0,8)}`,
                icon: 'https://cdn-icons-png.flaticon.com/512/3500/3500833.png'
            });
            notification.onclick = () => { window.focus(); goToOrders(); };
        }
    }
}