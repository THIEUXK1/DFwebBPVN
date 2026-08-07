using System;

namespace DFAgent;

/// <summary>
/// Số cân đọc được gần nhất, dùng chung giữa vòng đọc của <see cref="Worker"/> và
/// <see cref="LocalWeightServer"/> (ADR-013).
///
/// Vì sao phải có lớp này thay vì để máy chủ cục bộ tự gọi <see cref="ScaleReader"/>:
/// <c>ReadCurrentWeightWithStability()</c> KHÔNG phải hàm thuần — nó nạp từng lần đọc vào
/// StableFilter, mà "ổn định" được định nghĩa bằng hai lần đọc LIÊN TIẾP giống nhau. Gọi nó từ
/// luồng thứ hai là chen lần đọc lạ vào giữa chuỗi đó và làm hỏng chính cờ ổn định — cờ mà cả
/// luồng chốt bì lẫn điều kiện cho phép lưu đều dựa vào.
///
/// Nên: đúng MỘT nơi đọc cân (vòng lặp của Worker, nhịp 10ms), mọi nơi khác đọc lại bản chụp.
/// </summary>
public sealed class ScaleSnapshot
{
    /// <summary>
    /// Ghi đè cả bộ bằng MỘT phép gán tham chiếu (record bất biến) thay vì sửa từng trường:
    /// luồng đọc vì thế không bao giờ thấy được nửa cũ nửa mới — ví dụ số cân của lần đọc này
    /// ghép với cờ ổn định của lần trước. `volatile` bảo đảm luồng khác thấy ngay tham chiếu mới.
    /// </summary>
    private volatile BanChup? _moiNhat;

    public void Ghi(double weight, bool stable)
        => _moiNhat = new BanChup(weight, stable, DateTime.UtcNow);

    public BanChup? Doc() => _moiNhat;

    public sealed record BanChup(double Weight, bool Stable, DateTime DocLucUtc)
    {
        /// <summary>Tuổi số đọc (ms) — cùng ý nghĩa với `age_ms` của backend, xem DeviceController::getReading.</summary>
        public int TuoiMs => Math.Max(0, (int)Math.Round((DateTime.UtcNow - DocLucUtc).TotalMilliseconds));
    }
}
