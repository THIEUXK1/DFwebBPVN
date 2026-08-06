/**
 * Đối chiếu bản dựng phiếu cân ở TRÌNH DUYỆT (src/utils/weighSlip.ts) với bản ở SERVER
 * (WeighingJobController::buildSlipHtml).
 *
 *   node frontend/scripts/check-weigh-slip.mjs
 *
 * Vì sao cần: hai bản cố ý nhân bản logic. Khi mất mạng, mẻ nằm hàng đợi nhưng thợ vẫn phải in
 * được phiếu ngay — lúc đó phiếu do trình duyệt dựng. Nếu hai bản lệch nhau thì phiếu dán trên
 * hàng lúc mất mạng sẽ khác phiếu in lúc bình thường, và khác cả bản lưu trong print_jobs.
 *
 * KHÔNG chạm DB: nạp Laravel rồi gọi thẳng hàm tĩnh `buildSlipHtml` (hàm thuần, không ghi
 * PrintJob). Các item dựng bằng `new WeighingJobItem([...])` — model KHÔNG lưu.
 */
import { execFileSync } from 'node:child_process';
import { build } from 'esbuild';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(here, '..', '..');
const MOC_GIO = '02/08/2026 14:30:05'; // ghim cứng để hai bên so được

const CASES = [
  {
    ten: '3 dòng bình thường',
    header: { color: 'TESTQR', product_code: 'TC195904', machine_code: 'VD10', level_code: '220' },
    items: [
      { sequence_no: 1, rack_code: 'R1', material_code: 'DYE001', planned_weight: 12.5, tolerance_minus: 0.125, tolerance_plus: 0.125, actual_weight: 12.48 },
      { sequence_no: 2, rack_code: 'R2', material_code: 'DYE002', planned_weight: 8.3, tolerance_minus: 0.083, tolerance_plus: 0.083, actual_weight: 9.0 },
      { sequence_no: 3, rack_code: 'R3', material_code: 'DYE003', planned_weight: 25, tolerance_minus: 0.25, tolerance_plus: 0.25, actual_weight: null },
    ],
  },
  {
    // Rack rỗng in ra Ô TRỐNG, không thay bằng số thứ tự — VBA in thẳng `txt_rack{i}.Value`.
    ten: 'rack rỗng -> ô trống',
    header: { color: 'RED', product_code: 'P1', machine_code: 'M1', level_code: null },
    items: [
      { sequence_no: 4, rack_code: '', material_code: 'D1', planned_weight: 1, tolerance_minus: 0.01, tolerance_plus: 0.01, actual_weight: 1 },
      { sequence_no: 5, rack_code: null, material_code: 'D2', planned_weight: 2, tolerance_minus: 0.02, tolerance_plus: 0.02, actual_weight: 2 },
    ],
  },
  {
    // Từ 05/08/2026 số cân in KHÔNG còn dấu phẩy hàng nghìn (chỗ đó nhường cho cỡ chữ to hơn) —
    // ca này giữ lại để chốt hai bản cùng bỏ dấu phẩy, không phải bên bỏ bên giữ.
    ten: 'số lớn (không dấu phẩy hàng nghìn)',
    header: { color: 'BIG', product_code: 'P2', machine_code: 'M2', level_code: '9' },
    items: [
      { sequence_no: 1, rack_code: 'R1', material_code: 'D1', planned_weight: 1234567.891, tolerance_minus: 1, tolerance_plus: 1, actual_weight: 1234567.891 },
    ],
  },
  {
    ten: 'số âm',
    header: { color: 'NEG', product_code: 'P3', machine_code: 'M3', level_code: '1' },
    items: [
      { sequence_no: 1, rack_code: 'R1', material_code: 'D1', planned_weight: 10, tolerance_minus: 0.1, tolerance_plus: 0.1, actual_weight: -3.5 },
    ],
  },
  {
    ten: 'mã vật tư có dấu nháy kép (phải được escape thành &quot;)',
    header: { color: 'Q"T', product_code: 'P4', machine_code: 'M4', level_code: '2' },
    items: [
      { sequence_no: 1, rack_code: 'R"1', material_code: 'D"1', planned_weight: 5, tolerance_minus: 0.05, tolerance_plus: 0.05, actual_weight: 5 },
    ],
  },
  {
    ten: 'không có dòng nào',
    header: { color: 'EMPTY', product_code: 'P5', machine_code: 'M5', level_code: '' },
    items: [],
  },
  {
    // CÂN TAY (2026-08-02): không quét đơn nên phần đầu phiếu trống trơn, mọi dòng mang mã mồi
    // CANTAY, mục tiêu 0 và nhãn MANUAL. Nếu hai bản lệch nhau ở đúng hình dạng này thì tờ phiếu
    // cân tay in lúc mất mạng sẽ khác bản server lưu — mà cân tay chính là thứ hay dùng offline.
    ten: 'cân tay: đầu phiếu trống + nhãn MANUAL',
    header: { color: '', product_code: '', machine_code: 'N/A', level_code: '' },
    items: [
      { sequence_no: 1, rack_code: 'A1', material_code: 'CANTAY', planned_weight: 0, tolerance_minus: 0, tolerance_plus: 0, actual_weight: 8.3 },
      { sequence_no: 2, rack_code: null, material_code: 'CANTAY', planned_weight: 0, tolerance_minus: 0, tolerance_plus: 0, actual_weight: 0 },
      // Dòng cân tay chưa có số: phải là MANUAL, KHÔNG phải REJECTED — chốt đúng thứ tự nhánh.
      { sequence_no: 3, rack_code: 'A3', material_code: 'CANTAY', planned_weight: 0, tolerance_minus: 0, tolerance_plus: 0, actual_weight: null },
    ],
  },
  {
    ten: '9 dòng đầy đủ',
    header: { color: 'FULL', product_code: 'P6', machine_code: 'M6', level_code: '220' },
    items: Array.from({ length: 9 }, (_, i) => ({
      sequence_no: i + 1,
      rack_code: `R${i + 1}`,
      material_code: `DYE00${i + 1}`,
      planned_weight: (i + 1) * 3.33,
      tolerance_minus: (i + 1) * 0.0333,
      tolerance_plus: (i + 1) * 0.0333,
      actual_weight: (i + 1) * 3.33,
    })),
  },
];

// --- Bản trình duyệt ---
const outDir = mkdtempSync(join(tmpdir(), 'weighslip-'));
const outFile = join(outDir, 'weighSlip.mjs');
await build({
  entryPoints: [join(repoRoot, 'frontend', 'src', 'utils', 'weighSlip.ts')],
  outfile: outFile,
  format: 'esm',
  bundle: false,
  logLevel: 'silent',
});
const { buildSlipHtml, processStatus } = await import(pathToFileURL(outFile).href);

// --- Bản server ---
const root = repoRoot.replace(/\\/g, '/');
const phpScript = `
require '${root}/backend/vendor/autoload.php';
$app = require '${root}/backend/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$out = [];
foreach (json_decode(file_get_contents('php://stdin'), true) as $case) {
    $items = [];
    foreach ($case['items'] as $row) {
        // Model KHÔNG lưu xuống DB — chỉ để accessor process_status chạy đúng như thật.
        $item = new App\\Models\\WeighingJobItem();
        $item->sequence_no = $row['sequence_no'];
        $item->rack_code = $row['rack_code'];
        $item->material_code = $row['material_code'];
        $item->planned_weight = $row['planned_weight'];
        $item->tolerance_minus = $row['tolerance_minus'];
        $item->tolerance_plus = $row['tolerance_plus'];
        $item->actual_weight = $row['actual_weight'];
        $item->status = 'COMPLETED';
        $items[] = $item;
    }
    $header = $case['header'];
    $header['printed_at'] = '${MOC_GIO}';
    $out[] = [
        'tspl' => App\\Http\\Controllers\\WeighingJobController::buildSlipHtml($header, $items),
        'statuses' => array_map(fn ($i) => $i->process_status, $items),
    ];
}
echo json_encode($out);
`;

const phpOut = execFileSync('php', ['-r', phpScript], {
  input: JSON.stringify(CASES),
  encoding: 'utf8',
  maxBuffer: 10 * 1024 * 1024,
});
const phpResults = JSON.parse(phpOut);

let pass = 0;
let fail = 0;

/**
 * Chốt đúng HÌNH DẠNG của sheet mà `scaleform.btnPrint_Click` dựng ra — thứ mà phép so PHP/JS ở
 * dưới KHÔNG bắt được (hai bản sai giống hệt nhau vẫn "khớp"). Xem ghi chú đầu
 * `src/utils/weighSlip.ts` về từng con số.
 */
function loiCauTruc(html) {
  const rows = html.match(/<tr[^>]*>/g) || [];
  const loi = [];

  // 1 tiêu đề + 1 trống + 4 dòng COLOR/CODE/MACHINE/LEVEL + 1 trống + 1 tiêu đề bảng
  // + 9 dòng dữ liệu + 1 trống + 1 dòng Print time = 19.
  if (rows.length !== 19) loi.push(`phải có ĐÚNG 19 hàng (A1:E19), đang có ${rows.length}`);

  // VBA: `.Font.Bold` chỉ đặt cho A1:E1 và A7:E7 — dòng tiêu đề bảng (dòng 8) KHÔNG đậm.
  const dongDam = rows.map((r, i) => (r.includes('class="b"') ? i + 1 : 0)).filter(Boolean);
  if (JSON.stringify(dongDam) !== '[1,7]') loi.push(`dòng in đậm phải là [1,7], đang là [${dongDam}]`);

  if (!html.includes('<td>RACK</td><td>DYE CODE</td><td>WEIGHT</td><td>PROCESS</td><td>STATUS</td>')) {
    loi.push('thiếu đúng 5 cột RACK/DYE CODE/WEIGHT/PROCESS/STATUS');
  }
  if (!html.includes('<td>Print time:</td>')) loi.push('thiếu dòng "Print time:" ở cuối phiếu');

  const soO = (html.match(/<td>/g) || []).length;
  if (soO !== 19 * 5) loi.push(`phải có 19x5 = 95 ô, đang có ${soO}`);

  // 9 dòng dữ liệu (hàng 9..17) KHÔNG dòng nào được bỏ trống cột STATUS — VBA gọi
  // `GetProcessStatus` cho cả 9 ô txt_process, ô trắng ra REJECTED. Xem `statusInRaPhieu()`.
  const hangs = html.split('\n').filter((d) => d.startsWith('<tr'));
  hangs.slice(8, 17).forEach((d, i) => {
    if (!/<td>(ACCEPTED|REJECTED|MANUAL)<\/td><\/tr>$/.test(d)) {
      loi.push(`dòng dữ liệu ${i + 1} thiếu cột STATUS: ${d.slice(-60)}`);
    }
  });

  return loi;
}

CASES.forEach((c, i) => {
  const statuses = c.items.map((it) => processStatus(it));
  const js = buildSlipHtml({ ...c.header, printed_at: MOC_GIO }, c.items.map((it, idx) => ({
    ...it,
    process_status: statuses[idx],
  })));
  const php = phpResults[i];

  const statusOk = JSON.stringify(statuses) === JSON.stringify(php.statuses);
  const tsplOk = js === php.tspl;
  // Ca "9 dòng đầy đủ" là ca duy nhất có thể vượt 19 hàng nếu ai đó nới số dòng dữ liệu; các ca
  // còn lại phải luôn ra đúng 19 hàng dù đơn có mấy vật tư.
  const loiCau = c.items.length > 9 ? [] : loiCauTruc(js);

  if (statusOk && tsplOk && loiCau.length === 0) {
    pass++;
    console.log(`  PASS  ${c.ten}`);
    return;
  }

  fail++;
  console.log(`  FAIL  ${c.ten}`);
  loiCau.forEach((l) => console.log(`        cau truc: ${l}`));
  if (!statusOk) {
    console.log(`        process_status JS : ${JSON.stringify(statuses)}`);
    console.log(`        process_status PHP: ${JSON.stringify(php.statuses)}`);
  }
  if (!tsplOk) {
    const a = js.split('\n');
    const b = php.tspl.split('\n');
    for (let k = 0; k < Math.max(a.length, b.length); k++) {
      if (a[k] !== b[k]) {
        console.log(`        dong ${k + 1} JS : ${JSON.stringify(a[k])}`);
        console.log(`        dong ${k + 1} PHP: ${JSON.stringify(b[k])}`);
        break;
      }
    }
  }
});

rmSync(outDir, { recursive: true, force: true });
console.log(`\nKet qua: ${pass} pass, ${fail} fail`);
process.exit(fail === 0 ? 0 : 1);
