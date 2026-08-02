/**
 * Đối chiếu `utils/processColor` với ĐÚNG đoạn mã cũ nằm trong VbaRackGrid trước khi tách ra.
 *
 *   node frontend/scripts/check-process-color.mjs
 *
 * Vì sao đáng có: màu ô PROCESS không phải chuyện thẩm mỹ. Trong VBA gốc chính BackColor của ô
 * này là trạng thái nghiệp vụ — `btnSave_Click` đọc ngược màu nền để ghi ACCEPTED/REJECTED. Nay
 * quy tắc đó được DÙNG CHUNG bởi lưới 9 dòng và ô số DELTA cỡ lớn, nên một sai lệch ở ranh giới
 * 0.99/1.01 sẽ vừa tô sai màu vừa làm hai chỗ báo mâu thuẫn nhau.
 */
import { build } from 'esbuild';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(here, '..', '..');

/** Bản CŨ, chép nguyên văn từ VbaRackGrid.vue (git 5929b55) — không sửa một ký tự nào. */
function banCu(v, plannedWeight, coItem = true) {
  if (v === null) return null; // grid trả {} -> không đặt style
  const target = coItem ? Number(plannedWeight) : 0;
  if (!target || target <= 0) return '#FFFFFF';

  const ratio = v / target;
  let bg = '#FAE605';
  if (ratio >= 0.99 && ratio <= 1.01) bg = '#78FA14';
  else if (ratio > 1.01) bg = '#FF1400';

  return bg;
}

const outDir = mkdtempSync(join(tmpdir(), 'proccolor-'));
const outFile = join(outDir, 'processColor.mjs');
await build({
  entryPoints: [join(repoRoot, 'frontend', 'src', 'utils', 'processColor.ts')],
  outfile: outFile,
  format: 'esm',
  bundle: false,
  logLevel: 'silent',
});
const { processTone, processBackground } = await import(pathToFileURL(outFile).href);

const banMoi = (v, plannedWeight) =>
  v === null ? null : processBackground(processTone(v, plannedWeight));

// Mục tiêu 12.5g -> ranh giới ĐÚNG là 12.375 và 12.625
const MUC_TIEU = 12.5;
const soCan = [
  -5, -0.01, 0, 0.5, 6.25, 12.3, 12.374, 12.375, 12.3750001,
  12.4, 12.5, 12.6, 12.624, 12.625, 12.6250001, 12.7, 25, 1e6,
];

let pass = 0;
let fail = 0;
const kiemTra = (ten, cu, moi) => {
  if (cu === moi) { pass++; return; }
  fail++;
  console.log(`  FAIL  ${ten}: cu=${cu} moi=${moi}`);
};

for (const v of soCan) {
  kiemTra(`so can ${v} / muc tieu ${MUC_TIEU}`, banCu(v, MUC_TIEU), banMoi(v, MUC_TIEU));
}

// Quét dày quanh 2 ranh giới để không bỏ lọt lệch dấu >= / >
for (let i = -600; i <= 600; i++) {
  const v = MUC_TIEU * (1 + i / 100000);
  kiemTra(`quet ranh gioi ${v}`, banCu(v, MUC_TIEU), banMoi(v, MUC_TIEU));
}

// Mục tiêu bất thường: rỗng, 0, âm, không phải số, và dòng KHÔNG có vật tư
for (const t of [null, undefined, '', 0, -1, 'abc', NaN]) {
  kiemTra(`muc tieu ${JSON.stringify(t)}`, banCu(3.5, t), banMoi(3.5, t));
}
kiemTra('dong khong co vat tu', banCu(3.5, undefined, false), banMoi(3.5, undefined));
kiemTra('chua can (null)', banCu(null, MUC_TIEU), banMoi(null, MUC_TIEU));

// Số âm TUYỆT ĐỐI không được ăn nền xanh — chỗ bản dùng Abs() trước đây làm sai được
let amAnXanh = false;
for (let v = -100; v < 0; v += 0.37) {
  if (banMoi(v, MUC_TIEU) === '#78FA14') amAnXanh = true;
}
if (amAnXanh) { fail++; console.log('  FAIL  so AM an nen XANH'); }
else { pass++; console.log('  PASS  khong co so am nao an nen xanh'); }

rmSync(outDir, { recursive: true, force: true });
console.log(`\nKet qua: ${pass} pass, ${fail} fail`);
process.exit(fail === 0 ? 0 : 1);
