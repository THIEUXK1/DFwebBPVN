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
}
