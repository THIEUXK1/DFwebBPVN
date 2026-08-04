using DFAgent;
using Microsoft.Extensions.Configuration;
using Xunit;

namespace DFAgent.Tests;

/// <summary>
/// Ma tram cua may nay (Worker.ResolveWorkstationId).
///
/// Truoc 2026-08-01 bo cai MSI dong cung mot ma tram cho MOI may, nen hai tram can chay cung luc
/// ghi de so can cua nhau. Nay de trong Workstation:Id thi tu sinh tu TEN MAY — mot bo cai dung
/// cho moi may. Cau hinh tuong minh van phai duoc uu tien: may da cai tu truoc khong duoc doi ma
/// sau khi cap nhat Agent.
/// </summary>
public class WorkstationIdTests
{
    private static IConfiguration Config(params (string Key, string? Value)[] entries)
        => new ConfigurationBuilder()
            .AddInMemoryCollection(entries.Select(e => new KeyValuePair<string, string?>(e.Key, e.Value)))
            .Build();

    [Fact]
    public void Uu_tien_Id_cau_hinh_tuong_minh()
    {
        var config = Config(("Workstation:Id", "WS-WEIGH-SCALE"));

        Assert.Equal("WS-WEIGH-SCALE", Worker.ResolveWorkstationId(config));
    }

    [Fact]
    public void Cat_khoang_trang_thua_quanh_Id_cau_hinh()
    {
        var config = Config(("Workstation:Id", "  WS-CAN-01  "));

        Assert.Equal("WS-CAN-01", Worker.ResolveWorkstationId(config));
    }

    [Theory]
    [InlineData(null)]
    [InlineData("")]
    [InlineData("   ")]
    public void Id_trong_thi_sinh_tu_ten_may(string? cauHinh)
    {
        var config = Config(("Workstation:Id", cauHinh));

        string id = Worker.ResolveWorkstationId(config);

        Assert.StartsWith("WS-SCALE-", id);
        // Phai la dinh danh dung duoc lam khoa cache va doan URL: chi chu HOA, so va dau '-'.
        Assert.Matches("^[A-Z0-9-]+$", id);
    }

    [Fact]
    public void Hai_lan_goi_tren_cung_may_cho_cung_mot_ma()
    {
        var config = Config(("Workstation:Id", ""));

        // On dinh giua cac lan khoi dong service la dieu kien bat buoc: ma tram doi moi lan chay
        // se de lai mot ban ghi tram rac trong DB sau moi lan restart.
        Assert.Equal(Worker.ResolveWorkstationId(config), Worker.ResolveWorkstationId(config));
    }

    [Fact]
    public void Ma_sinh_ra_chua_ten_may_that()
    {
        var config = Config(("Workstation:Id", ""));

        string mongDoi = "WS-SCALE-" + new string(
            Environment.MachineName.ToUpperInvariant().Select(c => char.IsLetterOrDigit(c) ? c : '-').ToArray()
        ).Trim('-');

        Assert.Equal(mongDoi, Worker.ResolveWorkstationId(config));
    }

    [Fact]
    public void Khong_co_muc_Workstation_nao_van_sinh_duoc_ma()
    {
        // Cau hinh rong hoan toan (file appsettings.json bi mat/hong) van phai ra ma dung dinh
        // dang, thay vi nem loi lam service khong khoi dong duoc.
        string id = Worker.ResolveWorkstationId(Config());

        Assert.StartsWith("WS-SCALE-", id);
    }

    // ---- Tach 2 bo cai theo loai can (2026-08-03) ----------------------------------------
    //
    // Hai bo cai doc lap (can nho / can to) chay song song duoc tren CUNG mot may. Dieu kien
    // bat buoc: hai ban phai ra HAI ma tram khac nhau, neu khong ca hai ghi de len cung mot
    // khoa cache so can va man hinh nay doc phai so cua cai can kia.

    [Theory]
    [InlineData(null, "SMALL")]
    [InlineData("", "SMALL")]
    [InlineData("SMALL", "SMALL")]
    [InlineData("small", "SMALL")]
    [InlineData("LARGE", "LARGE")]
    [InlineData("  large  ", "LARGE")]
    [InlineData("linh tinh", "SMALL")]
    public void Loai_can_mac_dinh_la_SMALL_khi_cau_hinh_thieu_hoac_la(string? cauHinh, string mongDoi)
    {
        Assert.Equal(mongDoi, Worker.ResolveScaleKind(Config(("Workstation:ScaleKind", cauHinh))));
    }

    [Fact]
    public void Can_to_va_can_nho_tren_cung_may_ra_hai_ma_khac_nhau()
    {
        string maCanNho = Worker.ResolveWorkstationId(Config(("Workstation:Id", ""), ("Workstation:ScaleKind", "SMALL")));
        string maCanTo = Worker.ResolveWorkstationId(Config(("Workstation:Id", ""), ("Workstation:ScaleKind", "LARGE")));

        Assert.StartsWith("WS-SCALE-", maCanNho);
        Assert.StartsWith("WS-LARGE-", maCanTo);
        Assert.NotEqual(maCanNho, maCanTo);
    }

    // ---- Danh sach backend nhan so can (2026-08-03) -------------------------------------
    //
    // Frontend suy ra host API tu chinh URL trinh duyet dang mo, nen mo bang localhost va mo
    // bang IP server la hoi HAI backend khac nhau. Agent phai day duoc len ca hai, neu khong
    // mot trong hai dia chi vinh vien khong thay so can nao.

    [Fact]
    public void Khong_khai_bao_gi_thi_van_co_dung_mot_backend_mac_dinh()
    {
        Assert.Equal(new[] { "http://localhost:8500/api" }, Worker.ResolveBackendUrls(Config()));
    }

    [Fact]
    public void Backend_Url_don_le_van_chay_nhu_cu()
    {
        // Cau hinh tren may da cai KHONG duoc bi bo qua im lang sau khi cap nhat Agent.
        Assert.Equal(
            new[] { "http://10.0.60.209:8500/api" },
            Worker.ResolveBackendUrls(Config(("Backend:Url", "http://10.0.60.209:8500/api"))));
    }

    [Fact]
    public void Backend_Urls_dang_mang_duoc_uu_tien_hon_Backend_Url()
    {
        var config = Config(
            ("Backend:Url", "http://10.0.60.209:8500/api"),
            ("Backend:Urls:0", "http://127.0.0.1:8500/api"),
            ("Backend:Urls:1", "http://10.0.60.209:8500/api"));

        Assert.Equal(
            new[] { "http://127.0.0.1:8500/api", "http://10.0.60.209:8500/api" },
            Worker.ResolveBackendUrls(config));
    }

    [Fact]
    public void Loc_bo_muc_rong_dau_gach_cheo_thua_va_dia_chi_trung()
    {
        // Dia chi trung nhau se lam Agent gui 2 request y het nhau moi nhip day — vo ich, va
        // dau '/' thua sinh ra URL kieu ".../api//devices/readings".
        var config = Config(
            ("Backend:Urls:0", "http://127.0.0.1:8500/api/"),
            ("Backend:Urls:1", "   "),
            ("Backend:Urls:2", "http://127.0.0.1:8500/API"));

        Assert.Equal(new[] { "http://127.0.0.1:8500/api" }, Worker.ResolveBackendUrls(config));
    }

    // ---- File log PuTTY va buoc lui du phong (2026-08-04) -------------------------------
    //
    // Ban CAN TO doc file rieng putty_log_large.txt de khong dung chung cai can voi ban can nho.
    // Nhung may tram ngoai xuong chi co MOT PuTTY, ghi vao duong dan chuan cu putty_log.txt —
    // khong co buoc lui thi Agent can to khong doc duoc gi, va (truoc khi co /devices/hello)
    // tram can to khong bao gio hien ra.

    [Fact]
    public void Khong_khai_bao_gi_thi_doc_duong_dan_chuan_cu()
    {
        Assert.Equal(new[] { ScaleReader.DefaultLogFilePath }, ScaleReader.ResolveLogFilePaths(Config()));
    }

    [Fact]
    public void Khoa_cu_SimulationFilePath_van_duoc_doc()
    {
        // Cau hinh tren may da cai KHONG duoc bi bo qua im lang sau khi cap nhat Agent.
        Assert.Equal(
            new[] { @"D:\scale\cu.txt" },
            ScaleReader.ResolveLogFilePaths(Config(("Scale:SimulationFilePath", @"D:\scale\cu.txt"))));
    }

    [Fact]
    public void File_rieng_luon_dung_truoc_file_du_phong()
    {
        var config = Config(
            ("Scale:LogFilePath", @"D:\scale\putty_log_large.txt"),
            ("Scale:LogFilePathFallback", @"D:\scale\putty_log.txt"));

        Assert.Equal(
            new[] { @"D:\scale\putty_log_large.txt", @"D:\scale\putty_log.txt" },
            ScaleReader.ResolveLogFilePaths(config));
    }

    [Fact]
    public void Du_phong_trung_file_chinh_thi_bi_loai()
    {
        // Trung nhau se lam ResolveActiveLogFile bao "dang doc file du phong" oan, va canh bao
        // do chinh la thu de nguoi van hanh biet hai Agent co the dang doc chung mot cai can.
        var config = Config(
            ("Scale:LogFilePath", @"D:\scale\putty_log.txt"),
            ("Scale:LogFilePathFallback", @"d:\SCALE\PUTTY_LOG.TXT"));

        Assert.Single(ScaleReader.ResolveLogFilePaths(config));
    }

    [Fact]
    public void Ban_can_nho_khong_co_du_phong_thi_chi_doc_dung_mot_file()
    {
        // Ban can nho CO Y khong co buoc lui: no von da doc dung duong dan chuan cu.
        Assert.Equal(
            new[] { @"D:\scale\putty_log.txt" },
            ScaleReader.ResolveLogFilePaths(Config(("Scale:LogFilePath", @"D:\scale\putty_log.txt"))));
    }

    [Fact]
    public void Cau_hinh_cu_chua_co_ScaleKind_giu_nguyen_tien_to_WS_SCALE()
    {
        // May pilot dang chay ban Agent cu: cap nhat len ban moi KHONG duoc doi ma tram, neu
        // khong moi may tu sinh them mot tram moi va bo lai tram cu thanh rac trong DB.
        Assert.StartsWith("WS-SCALE-", Worker.ResolveWorkstationId(Config(("Workstation:Id", ""))));
    }
}
