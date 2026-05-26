<?php

return [
    // قائمة النطاقات المسموح لها بالوصول (من متغير البيئة)
    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:3000,http://localhost:5173')
        ),
        function($origin) { return !empty($origin); }
    ),

    // الطرق المسموح بها
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'HEAD'
    ],

    // الهيدرز المسموح بها في الطلب
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With'
    ],

    // الهيدرز التي يمكن كشفها للعميل
    'exposed_headers' => [
        'Authorization',
        'Content-Type'
    ],

    // السماح بإرسال الكوكيز مع الطلب
    'allow_credentials' => true,

    // مدة تخزين نتيجة طلب preflight (ثوانٍ)
    'max_age' => 86400,

    // السماح بكل origins (من متغير البيئة - تحذير: غير آمن في الإنتاج)
    'allow_all_origins' => ($_ENV['CORS_ALLOW_ALL'] ?? 'false') === 'true',
];
