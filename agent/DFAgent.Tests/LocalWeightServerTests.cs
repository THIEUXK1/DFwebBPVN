using System.Net;
using System.Net.Http;
using System.Net.Sockets;
using System.Text.Json;
using DFAgent;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace DFAgent.Tests;

/// <summary>
/// Duong can CUC BO (ADR-013): trinh duyet tren chinh may tram doc so can thang tu Agent thay vi
/// vong qua backend.
///
/// Kiem cai ma frontend that su phu thuoc vao, khong phai chi tiet noi bo:
///   - hinh dang JSON PHAI trung DeviceController::getReading (frontend dung MOT doan xu ly cho
///     ca hai nguon; lech mot ten truong la sinh ra hai nhanh phai giu dong bo)
///   - CORS + Access-Control-Allow-Private-Network — thieu la Chrome chan khi tho mo man hinh
///     bang IP rieng (http://10.0.60.209:3001), duong cuc bo chet cam
///   - chua doc duoc lan nao thi has_reading=false chu khong phai bia ra so 0.00
/// </summary>
public class LocalWeightServerTests : IAsyncLifetime
{
    // Cong rieng cho test, khong dung 8770/8771 de khong dam vao Agent that dang chay tren may dev.
    private const int CongTest = 8779;

    private readonly ScaleSnapshot _snapshot = new();
    private readonly LocalWeightServer _server;
    private readonly CancellationTokenSource _cts = new();
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(5) };

    public LocalWeightServerTests()
    {
        var config = new ConfigurationBuilder()
            .AddInMemoryCollection(new Dictionary<string, string?>
            {
                ["Local:Port"] = CongTest.ToString(),
                ["Workstation:Id"] = "WS-SCALE-TEST",
                ["Workstation:ScaleKind"] = "SMALL",
            })
            .Build();

        _server = new LocalWeightServer(NullLogger<LocalWeightServer>.Instance, config, _snapshot);
    }

    public async Task InitializeAsync()
    {
        await _server.StartAsync(_cts.Token);
        await ChoCongMo(CongTest);
    }

    /// <summary>
    /// StartAsync tra ve ngay khi ExecuteAsync gap await dau tien, chua chac listener da nghe.
    /// Cho bang cach go cua that thay vi Sleep mot con so doan mo — test chay tren may build cham
    /// van phai xanh, va khong ton them thoi gian tren may nhanh.
    /// </summary>
    private static async Task<bool> ChoCongMo(int cong, int soLan = 100)
    {
        for (int i = 0; i < soLan; i++)
        {
            try
            {
                using var probe = new TcpClient();
                await probe.ConnectAsync(IPAddress.Loopback, cong);
                return true;
            }
            catch (SocketException)
            {
                await Task.Delay(20);
            }
        }
        return false;
    }

    private async Task<JsonElement> Doc()
    {
        var body = await _http.GetStringAsync($"http://127.0.0.1:{CongTest}/weight");
        return JsonDocument.Parse(body).RootElement;
    }

    [Fact]
    public async Task Chua_doc_duoc_lan_nao_thi_has_reading_false()
    {
        var j = await Doc();

        Assert.False(j.GetProperty("has_reading").GetBoolean());
        // Giu 0.0 cho trung ban backend — client phai nhin has_reading/age_ms, khong nhin weight.
        Assert.Equal(0.0, j.GetProperty("weight").GetDouble());
        Assert.False(j.GetProperty("is_stable").GetBoolean());
        Assert.Equal(JsonValueKind.Null, j.GetProperty("age_ms").ValueKind);
    }

    [Fact]
    public async Task Tra_dung_so_can_vua_ghi_va_hinh_dang_trung_backend()
    {
        _snapshot.Ghi(12.34, stable: true);

        var j = await Doc();

        Assert.Equal("SUCCESS", j.GetProperty("status").GetString());
        Assert.Equal(12.34, j.GetProperty("weight").GetDouble(), 6);
        Assert.True(j.GetProperty("is_stable").GetBoolean());
        Assert.True(j.GetProperty("has_reading").GetBoolean());
        Assert.Equal("WS-SCALE-TEST", j.GetProperty("workstation_id").GetString());
        Assert.Equal("AGENT_LOCAL", j.GetProperty("source").GetString());
        // Vua ghi xong — tuoi phai la vai ms, khong phai null hay so am.
        Assert.InRange(j.GetProperty("age_ms").GetInt32(), 0, 2000);
    }

    [Fact]
    public async Task So_moi_ghi_de_so_cu()
    {
        _snapshot.Ghi(1.0, stable: false);
        _snapshot.Ghi(2.5, stable: true);

        var j = await Doc();

        Assert.Equal(2.5, j.GetProperty("weight").GetDouble(), 6);
        Assert.True(j.GetProperty("is_stable").GetBoolean());
    }

    [Fact]
    public async Task Preflight_tra_du_header_cho_Private_Network_Access()
    {
        // Dung request OPTIONS y het cai Chrome gui khi trang o IP rieng goi xuong loopback.
        var req = new HttpRequestMessage(HttpMethod.Options, $"http://127.0.0.1:{CongTest}/weight");
        req.Headers.Add("Origin", "http://10.0.60.209:3001");
        req.Headers.Add("Access-Control-Request-Method", "GET");
        req.Headers.Add("Access-Control-Request-Private-Network", "true");

        var res = await _http.SendAsync(req);

        Assert.Equal(HttpStatusCode.NoContent, res.StatusCode);
        Assert.Equal("*", string.Join("", res.Headers.GetValues("Access-Control-Allow-Origin")));
        // Thieu header nay la Chrome chan — day chinh la loi se rat kho doan neu no tai dien.
        Assert.Equal("true", string.Join("", res.Headers.GetValues("Access-Control-Allow-Private-Network")));
    }

    [Fact]
    public async Task Duong_la_tra_404()
    {
        var res = await _http.GetAsync($"http://127.0.0.1:{CongTest}/khong-co-duong-nay");

        Assert.Equal(HttpStatusCode.NotFound, res.StatusCode);
    }

    [Fact]
    public async Task Tat_bang_cau_hinh_thi_khong_mo_cong_nao()
    {
        int congKhac = CongTest + 1;
        var config = new ConfigurationBuilder()
            .AddInMemoryCollection(new Dictionary<string, string?>
            {
                ["Local:Enabled"] = "false",
                ["Local:Port"] = congKhac.ToString(),
            })
            .Build();

        var tat = new LocalWeightServer(NullLogger<LocalWeightServer>.Instance, config, new ScaleSnapshot());
        await tat.StartAsync(CancellationToken.None);

        // 3 lan go cua la du: neu no CO mo cong thi mo ngay, khong phai doi 2 giay.
        Assert.False(await ChoCongMo(congKhac, soLan: 3));

        await tat.StopAsync(CancellationToken.None);
    }

    [Fact]
    public async Task Ban_RACK_ONLY_khong_mo_cong_du_cau_hinh_bat()
    {
        // appsettings.large-inout.json la RACK_ONLY: no KHONG doc can. Neu no mo cong thi vua
        // phuc vu mot ban chup rong vinh vien, vua co the GIANH MAT cong cua ban DFAgentLarge cai
        // cung may (khac service name nen chay song song duoc). Cau hinh da tat san, day la lop
        // chan thu hai trong code — de cau hinh sau nay co go nham cung khong hong.
        int congKhac = CongTest + 2;
        var config = new ConfigurationBuilder()
            .AddInMemoryCollection(new Dictionary<string, string?>
            {
                ["Workstation:Role"] = "RACK_ONLY",
                ["Local:Enabled"] = "true",   // bat tuong minh — van phai KHONG mo
                ["Local:Port"] = congKhac.ToString(),
            })
            .Build();

        var rackOnly = new LocalWeightServer(NullLogger<LocalWeightServer>.Instance, config, new ScaleSnapshot());
        await rackOnly.StartAsync(CancellationToken.None);

        Assert.False(await ChoCongMo(congKhac, soLan: 3));

        await rackOnly.StopAsync(CancellationToken.None);
    }

    public async Task DisposeAsync()
    {
        _cts.Cancel();
        try { await _server.StopAsync(CancellationToken.None); } catch { }
        _http.Dispose();
        _cts.Dispose();
    }
}
