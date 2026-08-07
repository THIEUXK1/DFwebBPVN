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

    // Đường đăng nhập MES. Production SSO dùng 'sys/ssologin'; bản MES nội bộ
    // (vd http://192.168.250.147:38085/nhmes) dùng 'sys/login'.
    'login_path' => env('MES_LOGIN_PATH', 'sys/ssologin'),

    // Chứng chỉ HTTPS của MES hợp lệ (đã kiểm tra 2026-07-31) nên mặc định vẫn verify.
    // Chỉ tắt nếu nhà máy đổi sang self-signed — tắt verify là hạ mức bảo mật, không
    // đặt false chỉ vì "cho nhanh".
    'verify_ssl' => env('MES_VERIFY_SSL', true),

    // 5.5MB/lần tải nên timeout phải rộng hơn mặc định.
    'timeout' => env('MES_TIMEOUT', 120),

    // Tài khoản/endpoint RIÊNG cho module 车间生产 (eBatchLine/batchView) — nguồn giờ kết
    // thúc nhuộm thật (mes:sync-batch-completions). Tách ra vì tài khoản đồng bộ MÀU
    // (getDataForGantt) hiện KHÔNG có quyền vào module này (xác nhận 2026-08-07: cùng phiên
    // login production, getDataForGantt trả 3433 dòng nhưng batchView trả trang login). Mỗi
    // trường để trống -> tự dùng lại giá trị cấu hình chính ở trên.
    'batch' => [
        'base_url' => env('MES_BATCH_BASE_URL'),
        'username' => env('MES_BATCH_USERNAME'),
        'password' => env('MES_BATCH_PASSWORD'),
        'login_path' => env('MES_BATCH_LOGIN_PATH'),

        // batchView phụ thuộc BỐI CẢNH NHÀ MÁY của tài khoản: thiếu thì MES trả
        // {"code":500,"msg":"未找到工厂，请添加权限！"} (không tìm thấy nhà máy). Điền mã nhà
        // máy + org filter đúng của tài khoản (lấy từ Network trên trang 车间生产 thật) vào
        // đây khi đã có tài khoản đủ quyền. Để trống -> không gửi (chỉ chạy được nếu tài
        // khoản đã mặc định gắn 1 nhà máy).
        'factory_code' => env('MES_BATCH_FACTORY_CODE'),
        'org_filter_value' => env('MES_BATCH_ORG_FILTER'),
    ],
];
