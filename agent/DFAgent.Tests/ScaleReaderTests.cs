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
        double result = reader.CleanWeight("12,ST,GS,+000010.5g");
        Assert.Equal(10.5, result, precision: 6);
    }

    [Fact]
    public void TV2_CleanWeight_KhongCoPrefixSo_VanDungNhuCu()
    {
        var reader = NewReader();
        double result = reader.CleanWeight("ST,GS,+000010.5g");
        Assert.Equal(10.5, result, precision: 6);
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
        Assert.Equal(10.5, weight1, precision: 6);
        Assert.False(stable1);

        var (weight2, stable2) = reader.ReadWeightWithStability("12,ST,GS,+000010.5g");
        Assert.Equal(10.5, weight2, precision: 6);
        Assert.True(stable2);
    }

    [Fact]
    public void CleanWeight_ChuoiRong_TraVe0()
    {
        var reader = NewReader();
        Assert.Equal(0.0, reader.CleanWeight(""));
    }
}
