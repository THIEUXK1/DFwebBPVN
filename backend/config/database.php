<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => \App\Services\DbHostResolver::resolve(),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            // LAN nội bộ tới server vật lý 10.0.60.209 thỉnh thoảng chập chờn (mất gói tin,
            // không phải bị từ chối kết nối hẳn) — không set timeout thì PDO có thể treo rất
            // lâu ở bước bắt tay TCP. Vì backend này chạy `php artisan serve` đơn luồng và
            // cũng đang phục vụ luôn các Local Agent ngoài xưởng trong giai đoạn Parallel Run,
            // 1 request bị treo do mất mạng sẽ đơ toàn bộ app cho mọi người, giống lỗi SSE và
            // ODBC BPDB đã sửa trước đó. PDO::ATTR_TIMEOUT được pdo_pgsql tôn trọng làm
            // connect_timeout (khác pdo_odbc không tôn trọng nó).
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
                // Kết nối BỀN — tái dùng kết nối đã mở thay vì bắt tay lại từ đầu mỗi request.
                // Đo thật 2026-08-02 trên chính đường mạng này: mở kết nối mới tốn ~212ms,
                // tái dùng kết nối bền chỉ ~57ms ⇒ **tiết kiệm ~155ms cho MỌI request có chạm
                // DB**, kể cả những request bé nhất. Chi phí này lớn hơn hẳn tổng thời gian
                // chạy câu lệnh của phần lớn endpoint (ping tới DB ~13ms, mỗi query ~33ms).
                //
                // Đánh đổi đã cân nhắc: nếu một request chết giữa transaction vì lỗi nghiêm
                // trọng (hết bộ nhớ/timeout) thì PDO KHÔNG tự rollback trên kết nối bền, và
                // kết nối đó được trả về pool khi transaction còn mở. Chấp nhận được vì mọi
                // đường ghi đều bọc trong DB::transaction() (tự rollback khi có exception), và
                // đổi lại là mức cải thiện thấy được bằng mắt ở trạm cân. Đặt DB_PERSISTENT=false
                // trong .env để tắt ngay mà không phải sửa code nếu gặp vấn đề.
                PDO::ATTR_PERSISTENT => (bool) env('DB_PERSISTENT', true),
            ],
            // Server DB dùng chung (10.0.60.209) có session TimeZone mặc định là
            // 'Asia/Bangkok' (UTC+7), không phải UTC như app.timezone của Laravel. Cột
            // timestamptz khi PHP ghi now() (UTC, không kèm offset) xuống bị Postgres hiểu
            // nhầm là giờ Bangkok rồi tự trừ lùi 7 tiếng lúc lưu — khiến toàn bộ timestamp
            // ghi mới đều lệch 7 tiếng. Ép session về UTC ngay khi Laravel kết nối để khớp
            // với app.timezone, không đụng tới cấu hình server DB dùng chung. Dữ liệu cũ đã
            // ghi trước khi sửa (nếu có) vẫn giữ nguyên, không bị đụng tới bởi thay đổi này.
            'timezone' => 'UTC',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
