using DFAgent;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace DFAgent.Tests;

/// <summary>
/// Test vectors TV1/TV2/TV3 từ .claude/p0-analysis/p0-c-scale-algorithm.md Phần B —
/// khóa lại hành vi ExtractLastNumber (PB-1) và StableFilter (PB-2) đã sửa 2026-07-17,
/// chống regression về lại bug "lấy số ĐẦU TIÊN" / "stable hard-code true".
/// </summary>
public class ScaleReaderTests
{
    private static ScaleReader NewReader()
    {
        var config = new ConfigurationBuilder().Build();
        return new ScaleReader(NullLogger<ScaleReader>.Instance, config);
    }

    [Fact]
    public void TV1_CleanWeight_TrichSoCuoiCung_KhongPhaiSoDauTien()
    {
        var reader = NewReader();
        // "12,ST,GS,+000010.5g" — VBA ExtractLastNumber phải trả +000010.5 (số CUỐI),
        // không phải "12" (mã trạm đứng đầu chuỗi).
        double? result = reader.CleanWeight("12,ST,GS,+000010.5g");
        Assert.Equal(10.5, result!.Value, precision: 6);
    }

    [Fact]
    public void TV2_CleanWeight_KhongCoPrefixSo_VanDungNhuCu()
    {
        var reader = NewReader();
        double? result = reader.CleanWeight("ST,GS,+000010.5g");
        Assert.Equal(10.5, result!.Value, precision: 6);
    }

    [Fact]
    public void TV3_StableFilter_CanToiThieu2LanDocGiongHetChuoi()
    {
        var reader = NewReader();

        // Lần 1: "12.30" — lần đọc đầu tiên, chưa có gì để so sánh -> chưa ổn định.
        Assert.False(reader.StableFilter("12.30"));

        // Lần 2: "12.3" != "12.30" (so sánh CHUỖI tuyệt đối, không phải giá trị số)
        // -> vẫn chưa ổn định dù cùng giá trị vật lý.
        Assert.False(reader.StableFilter("12.3"));

        // Lần 3: "12.3" == "12.3" (so với lần 2) -> ổn định từ đây.
        Assert.True(reader.StableFilter("12.3"));
    }

    [Fact]
    public void StableFilter_ThayDoiGiaTri_MatTrangThaiOnDinh()
    {
        var reader = NewReader();

        Assert.False(reader.StableFilter("5.0"));
        Assert.True(reader.StableFilter("5.0"));

        // Giá trị đổi khác -> phải reset về chưa ổn định, không giữ trạng thái ổn định cũ.
        Assert.False(reader.StableFilter("5.2"));
    }

    [Fact]
    public void ReadWeightWithStability_TraVeCaGiaTriVaCoOnDinh()
    {
        var reader = NewReader();

        var (weight1, stable1) = reader.ReadWeightWithStability("12,ST,GS,+000010.5g");
        Assert.Equal(10.5, weight1!.Value, precision: 6);
        Assert.False(stable1);

        var (weight2, stable2) = reader.ReadWeightWithStability("12,ST,GS,+000010.5g");
        Assert.Equal(10.5, weight2!.Value, precision: 6);
        Assert.True(stable2);
    }

    /// <summary>
    /// Cân to phát xen kẽ một dòng số thật rồi một dòng "0000000" (log máy trạm 09/08/2026).
    /// Dòng toàn chữ số đó parse ra 0.0 hợp lệ, nên "dòng cuối không rỗng" cứ một nhịp trúng số
    /// thật một nhịp trúng 0 -> số cân nhấp nháy và StableFilter không bao giờ chốt được.
    /// </summary>
    [Theory]
    [InlineData("US,+000466.6  g", true)]
    [InlineData("ST,-008359.3  g", true)]
    [InlineData("12,ST,GS,+000010.5g", true)]
    [InlineData("0000000", false)]
    [InlineData("0", false)]
    [InlineData("   ", false)]
    [InlineData("", false)]
    public void LaDongSoCan_LoaiDungDongNhieuToanChuSo(string line, bool mongDoi)
    {
        Assert.Equal(mongDoi, ScaleReader.LaDongSoCan(line));
    }

    /// <summary>
    /// Cờ ST/US nhảy liên tục ngay cả khi con số đứng yên. StableFilter phải so trên TOKEN SỐ
    /// (đúng VBA `StableFilter(rawNum)`), không phải trên cả dòng thô — nếu không thì cân đứng
    /// yên rồi mà Agent vẫn báo chưa ổn định, và bấm NEXT không chốt được bì.
    /// </summary>
    [Fact]
    public void ReadWeightWithStability_DoiCoST_US_NhungSoKhongDoi_VanOnDinh()
    {
        var reader = NewReader();

        Assert.False(reader.ReadWeightWithStability("US,-008359.3  g").IsStable);

        var (weight, stable) = reader.ReadWeightWithStability("ST,-008359.3  g");
        Assert.Equal(-8359.3, weight!.Value, precision: 6);
        Assert.True(stable);
    }

    /// <summary>
    /// TV6 (sửa 2026-07-17): chuỗi rỗng trả null chứ KHÔNG phải 0.0 — 0.0 là kết quả cân hợp
    /// lệ (cân đang rỗng thật), phải phân biệt được với "không đọc được gì".
    /// </summary>
    [Fact]
    public void CleanWeight_ChuoiRong_TraVeNull()
    {
        var reader = NewReader();
        Assert.Null(reader.CleanWeight(""));
    }

    /// <summary>
    /// Cân thật cắm qua RS232: SerialPort.ReadExisting() trả về đúng nội dung buffer tại thời
    /// điểm gọi, cắt giữa dòng là chuyện bình thường. Mảnh cụt tuyệt đối không được biến thành
    /// một số cân hợp lệ (ở đây: "+00001" -> 1, trong khi số thật là 10.5).
    /// </summary>
    [Fact]
    public void IngestSerialData_ChunkCatGiuaDong_KhongSinhSoCanSai()
    {
        var reader = NewReader();

        reader.IngestSerialData("12,ST,GS,+00001");
        Assert.Null(reader.LatestSerialReading.Weight);

        reader.IngestSerialData("0.5g\r\n");
        Assert.Equal(10.5, reader.LatestSerialReading.Weight!.Value, precision: 6);
    }

    [Fact]
    public void IngestSerialData_NhieuDongTrongMotChunk_LayDongCuoiCung()
    {
        var reader = NewReader();

        reader.IngestSerialData("12,ST,GS,+000010.5g\r\n12,ST,GS,+000012.3g\r\n");
        Assert.Equal(12.3, reader.LatestSerialReading.Weight!.Value, precision: 6);
    }

    /// <summary>
    /// TV6: dòng rác không được ghi đè số hợp lệ gần nhất bằng 0.0 — "cân đang đọc 0kg" và
    /// "không đọc được gì" là hai chuyện khác nhau.
    /// </summary>
    [Fact]
    public void IngestSerialData_DongRac_GiuNguyenSoHopLeGanNhat()
    {
        var reader = NewReader();

        reader.IngestSerialData("12,ST,GS,+000010.5g\r\n");
        reader.IngestSerialData("<<<ERR>>>\r\n");

        Assert.Equal(10.5, reader.LatestSerialReading.Weight!.Value, precision: 6);
    }

    /// <summary>
    /// StableFilter phải chạy theo TỪNG DÒNG cân gửi ra (đúng nghĩa "2 lần đọc liên tiếp" của
    /// VBA Mod_delta_raw), không phải theo mỗi vòng poll của Worker.
    /// </summary>
    [Fact]
    public void IngestSerialData_HaiDongGiongNhau_DanhDauOnDinh()
    {
        var reader = NewReader();

        reader.IngestSerialData("12,ST,GS,+000010.5g\r\n");
        Assert.False(reader.LatestSerialReading.IsStable);

        reader.IngestSerialData("12,ST,GS,+000010.5g\r\n");
        Assert.True(reader.LatestSerialReading.IsStable);
    }

    // ---------------------------------------------------------------------------------------
    // Đọc đuôi file log PuTTY (chế độ Scale:Source=PUTTY_LOG — cách hệ Excel VBA cũ vẫn chạy).
    // Nhịp đọc nay là 10ms nên mọi khiếm khuyết của hàm đọc file đều bị nhân lên 100 lần/giây.
    // ---------------------------------------------------------------------------------------

    private static ScaleReader NewReaderWith(params (string Key, string Value)[] settings)
    {
        var config = new ConfigurationBuilder()
            .AddInMemoryCollection(settings.Select(s => new KeyValuePair<string, string?>(s.Key, s.Value)))
            .Build();
        return new ScaleReader(NullLogger<ScaleReader>.Instance, config);
    }

    /// <summary>
    /// Đường dẫn file log PuTTY trên máy trạm cân, người dùng chốt 2026-08-01. Khoá lại để việc
    /// đổi đường dẫn phải là hành động có chủ ý, không trôi đi lúc refactor.
    /// </summary>
    [Fact]
    public void DuongDanLogMacDinh_DungTramCan()
    {
        Assert.Equal(@"D:\scale\putty_log.txt", ScaleReader.DefaultLogFilePath);
    }

    /// <summary>
    /// Máy đã cài đang dùng khoá cũ SimulationFilePath. Sau khi cập nhật Agent, khoá cũ vẫn phải
    /// có hiệu lực — nếu không, Agent sẽ âm thầm quay về đường dẫn mặc định và đọc nhầm file.
    /// </summary>
    [Fact]
    public void CauHinhCu_SimulationFilePath_VanCoHieuLuc()
    {
        string path = WriteTempLog("12,ST,GS,+000010.5g\r\n");
        try
        {
            var reader = NewReaderWith(
                ("Scale:Source", "PUTTY_LOG"),
                ("Scale:SimulationFilePath", path));

            Assert.Equal(10.5, reader.ReadCurrentWeightWithStability().Weight!.Value, precision: 6);
        }
        finally { File.Delete(path); }
    }

    /// <summary>LogFilePath (khoá mới) phải thắng SimulationFilePath khi có cả hai.</summary>
    [Fact]
    public void LogFilePath_UuTienHonKhoaCu()
    {
        string moi = WriteTempLog("12,ST,GS,+000010.5g\r\n");
        string cu = WriteTempLog("12,ST,GS,+000099.9g\r\n");
        try
        {
            var reader = NewReaderWith(
                ("Scale:Source", "PUTTY_LOG"),
                ("Scale:LogFilePath", moi),
                ("Scale:SimulationFilePath", cu));

            Assert.Equal(10.5, reader.ReadCurrentWeightWithStability().Weight!.Value, precision: 6);
        }
        finally { File.Delete(moi); File.Delete(cu); }
    }

    /// <summary>
    /// Log THẬT của cân to (máy trạm, 09/08/2026): cứ một dòng số là một dòng "0000000".
    ///
    /// Hai điều phải đúng cùng lúc:
    ///   · Đọc ra 610.8 chứ không phải 0 — dòng nhiễu bị bỏ qua khi chọn dòng cuối.
    ///   · Hai lần đọc liên tiếp phải thành ỔN ĐỊNH, kể cả khi cờ ST/US nhảy — nếu không thì bấm
    ///     NEXT không chốt được bì.
    /// </summary>
    [Fact]
    public void LogXenKeDongNhieu_DocDungSoCuoiVaChotDuocOnDinh()
    {
        string path = WriteTempLog(
            "US,+000611.3  g\r\n0000000\r\nUS,+000610.8  g\r\n0000000\r\n");
        try
        {
            var reader = NewReaderWith(("Scale:Source", "PUTTY_LOG"), ("Scale:LogFilePath", path));

            var lan1 = reader.ReadCurrentWeightWithStability();
            Assert.Equal(610.8, lan1.Weight!.Value, precision: 6);
            Assert.False(lan1.IsStable); // lần đọc đầu, chưa có gì để so

            // Nhịp đọc 10ms nhanh hơn nhịp cân phát dòng mới -> đọc lại đúng dòng đó = ổn định.
            Assert.True(reader.ReadCurrentWeightWithStability().IsStable);

            // Cân đổi cờ trạng thái nhưng số không đổi -> vẫn phải là ổn định.
            File.AppendAllText(path, "ST,+000610.8  g\r\n0000000\r\n");
            var lan3 = reader.ReadCurrentWeightWithStability();
            Assert.Equal(610.8, lan3.Weight!.Value, precision: 6);
            Assert.True(lan3.IsStable);
        }
        finally { File.Delete(path); }
    }

    private static string WriteTempLog(string content)
    {
        string path = Path.Combine(Path.GetTempPath(), $"df_putty_{Guid.NewGuid():N}.log");
        File.WriteAllText(path, content);
        return path;
    }

    [Fact]
    public void PuttyLog_LayDongCuoiCungKhongRong()
    {
        string path = WriteTempLog("12,ST,GS,+000009.1g\r\n12,ST,GS,+000010.5g\r\n");
        try
        {
            Assert.Equal("12,ST,GS,+000010.5g", ScaleReader.ReadLastCompleteLine(path));
        }
        finally { File.Delete(path); }
    }

    /// <summary>
    /// Khác biệt A.1 với VBA (p0-c-scale-algorithm.md): VBA ReadLastLineFast bỏ qua dòng rỗng
    /// (`If Len(s) > 0`), bản .NET cũ lấy dòng vật lý cuối nên trả về "" khi file kết thúc bằng
    /// dòng trắng — rồi bị hiểu thành "cân đọc 0kg".
    /// </summary>
    [Fact]
    public void PuttyLog_FileKetThucBangDongTrang_VanLayDuocSoThat()
    {
        string path = WriteTempLog("12,ST,GS,+000010.5g\r\n\r\n\r\n");
        try
        {
            Assert.Equal("12,ST,GS,+000010.5g", ScaleReader.ReadLastCompleteLine(path));
        }
        finally { File.Delete(path); }
    }

    /// <summary>
    /// Điểm rủi ro lớn nhất của nhịp 10ms: chộp đúng lúc PuTTY mới ghi được nửa dòng. Mảnh cụt
    /// "12,ST,GS,+0000" sẽ được CleanWeight parse thành 0 — một số cân HỢP LỆ nhưng SAI. Phải
    /// bỏ qua đuôi chưa có ký tự xuống dòng và giữ nguyên dòng trọn vẹn trước đó.
    /// </summary>
    [Fact]
    public void PuttyLog_DongCuoiDangGhiDo_BoQuaMangCut()
    {
        string path = WriteTempLog("12,ST,GS,+000010.5g\r\n12,ST,GS,+0000");
        try
        {
            string line = ScaleReader.ReadLastCompleteLine(path);
            Assert.Equal("12,ST,GS,+000010.5g", line);
            Assert.Equal(10.5, NewReader().CleanWeight(line)!.Value, precision: 6);
        }
        finally { File.Delete(path); }
    }

    /// <summary>
    /// File log PuTTY phình dần suốt ca. Chi phí đọc phải KHÔNG phụ thuộc kích thước file, nếu
    /// không thì nhịp 10ms sẽ làm nghẹt I/O máy trạm (bản cũ dùng File.ReadAllLines đọc cả file).
    /// </summary>
    [Fact]
    public void PuttyLog_FileRatLon_VanLayDungDongCuoiVaNhanh()
    {
        var big = new System.Text.StringBuilder();
        for (int i = 0; i < 200_000; i++) big.Append("12,ST,GS,+000001.0g\r\n");
        big.Append("12,ST,GS,+000010.5g\r\n");

        string path = WriteTempLog(big.ToString());
        try
        {
            var sw = System.Diagnostics.Stopwatch.StartNew();
            for (int i = 0; i < 100; i++) ScaleReader.ReadLastCompleteLine(path);
            sw.Stop();

            Assert.Equal("12,ST,GS,+000010.5g", ScaleReader.ReadLastCompleteLine(path));
            // 100 lần đọc = đúng 1 giây chạy thật ở nhịp 10ms. File ~4MB: bản đọc cả file mất
            // hàng giây, bản seek-tới-cuối phải xong trong vài chục ms.
            Assert.True(sw.ElapsedMilliseconds < 500, $"100 lần đọc mất {sw.ElapsedMilliseconds}ms — quá chậm cho nhịp 10ms");
        }
        finally { File.Delete(path); }
    }

    /// <summary>
    /// PuTTY giữ file mở để ghi trong suốt phiên — mở với FileShare mặc định sẽ ném
    /// IOException ngay lần đọc đầu tiên và Agent không bao giờ thấy số cân nào.
    /// </summary>
    [Fact]
    public void PuttyLog_FileDangDuocGhiBoiTienTrinhKhac_VanDocDuoc()
    {
        string path = WriteTempLog("12,ST,GS,+000010.5g\r\n");
        try
        {
            using var writer = new FileStream(path, FileMode.Append, FileAccess.Write, FileShare.Read);
            Assert.Equal("12,ST,GS,+000010.5g", ScaleReader.ReadLastCompleteLine(path));
        }
        finally { File.Delete(path); }
    }
}
