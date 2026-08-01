<?php
// backend/config/mes.php
//
// Kết nối VN-MES (hệ MES của nhà máy) — hiện CHỈ dùng để đồng bộ bảng tra màu
// (App\Services\Mes\MesSedoClient + lệnh `mes:sync-color-swatches`). Đây là tích hợp
// CHỈ ĐỌC: không có endpoint nào của DF ghi ngược vào MES.
//
// Tài khoản để trong .env (đã gitignore), không hardcode ở đây.

return [
    'base_url' => env('MES_BASE_URL', 'https://f.mes.bestpacific.vn/mes'),
    'username' => env('MES_USERNAME'),
    'password' => env('MES_PASSWORD'),

    // Chứng chỉ HTTPS của MES hợp lệ (đã kiểm tra 2026-07-31) nên mặc định vẫn verify.
    // Chỉ tắt nếu nhà máy đổi sang self-signed — tắt verify là hạ mức bảo mật, không
    // đặt false chỉ vì "cho nhanh".
    'verify_ssl' => env('MES_VERIFY_SSL', true),

    // 5.5MB/lần tải nên timeout phải rộng hơn mặc định.
    'timeout' => env('MES_TIMEOUT', 120),
];
