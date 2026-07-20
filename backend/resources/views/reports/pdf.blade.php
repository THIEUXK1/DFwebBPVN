<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
        th { background-color: #eee; font-weight: bold; }
        tr:nth-child(even) td { background-color: #fafafa; }
        .footer { margin-top: 16px; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="subtitle">{{ $subtitle }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Không có dữ liệu trong khoảng thời gian đã chọn.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">Dự án DF - Xuất báo cáo tự động lúc {{ now()->format('d/m/Y H:i:s') }}</div>
</body>
</html>
