<?php
// backend/config/colorservice.php
//
// Cấu hình kết nối BPDB (Color Service) — chỉ dùng bởi App\Services\ColorService\
// BpdbReadOnlyClient. KHÔNG đăng ký làm connection trong config/database.php vì Laravel
// không có driver "odbc" built-in cho query builder/Eloquent; client tự quản lý PDO
// riêng để dễ audit (không có ORM nào có thể vô tình sinh câu lệnh ghi).
//
// Tên biến BPDB_MONITOR_* (đổi từ CS_DB_* cũ ngày 2026-07-20) khớp tài khoản
// bpdb_monitor_readonly do DBA cấp — xem sql_migration/colorservice_handoff/.

return [
    'odbc_driver' => env('BPDB_MONITOR_ODBC_DRIVER', 'ODBC Driver 13 for SQL Server'),
    'host' => env('BPDB_MONITOR_HOST'),
    'port' => env('BPDB_MONITOR_PORT', 1433),
    'database' => env('BPDB_MONITOR_DATABASE', 'BPDB'),
    'username' => env('BPDB_MONITOR_USERNAME'),
    'password' => env('BPDB_MONITOR_PASSWORD'),
    'encrypt' => env('BPDB_MONITOR_ENCRYPT', false),
    'trust_server_certificate' => env('BPDB_MONITOR_TRUST_SERVER_CERTIFICATE', true),
];
