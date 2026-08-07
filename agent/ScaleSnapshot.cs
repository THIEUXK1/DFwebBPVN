using System;
using System.Threading;
using System.Threading.Tasks;

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

    /// <summary>
    /// Chuông báo "vừa có số mới", cho <see cref="LocalWeightServer"/> đẩy SSE ngay lúc số đổi
    /// thay vì hỏi vòng.
    ///
    /// Vì sao TaskCompletionSource dùng-một-lần rồi thay mới, thay vì một sự kiện C# thường: bên
    /// chờ là code BẤT ĐỒNG BỘ (`await`), mà `event` chỉ gọi lại đồng bộ ngay trên luồng đọc cân —
    /// đẩy một gói qua socket ngay trong đó là chặn đúng cái vòng lặp 10ms không được phép chặn.
    ///
    /// <c>RunContinuationsAsynchronously</c> BẮT BUỘC vì cùng lý do: thiếu nó thì `TrySetResult`
    /// chạy tiếp phần `await` của luồng SSE NGAY TRÊN luồng vừa gọi <see cref="Ghi"/>.
    /// </summary>
    private TaskCompletionSource<bool> _chuong = new(TaskCreationOptions.RunContinuationsAsynchronously);

    public void Ghi(double weight, bool stable)
    {
        _moiNhat = new BanChup(weight, stable, DateTime.UtcNow);
        // Thay chuông TRƯỚC khi rung: người chờ tiếp theo phải bắt được cái mới, không phải cái
        // vừa kêu xong (nếu không họ chờ mãi một chuông đã kêu rồi).
        Interlocked.Exchange(ref _chuong, new TaskCompletionSource<bool>(TaskCreationOptions.RunContinuationsAsynchronously))
            .TrySetResult(true);
    }

    /// <summary>
    /// Task hoàn tất ở lần <see cref="Ghi"/> KẾ TIẾP. Phải lấy Task này TRƯỚC khi đọc
    /// <see cref="Doc"/>, nếu không có khe hở: số mới ghi vào đúng giữa hai lệnh thì bên chờ vừa
    /// đọc được số cũ vừa nằm chờ một chuông đã rung xong.
    /// </summary>
    public Task ChoSoMoi() => Volatile.Read(ref _chuong).Task;

    public BanChup? Doc() => _moiNhat;

    public sealed record BanChup(double Weight, bool Stable, DateTime DocLucUtc)
    {
        /// <summary>Tuổi số đọc (ms) — cùng ý nghĩa với `age_ms` của backend, xem DeviceController::getReading.</summary>
        public int TuoiMs => Math.Max(0, (int)Math.Round((DateTime.UtcNow - DocLucUtc).TotalMilliseconds));
    }
}
