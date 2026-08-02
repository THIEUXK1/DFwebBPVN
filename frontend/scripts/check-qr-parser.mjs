/**
 * Đối chiếu bản parse QR ở TRÌNH DUYỆT (src/utils/qrDyeParser.ts) với bản ở SERVER
 * (QrPayloadService::parseDyeScan). Hai bản này cố ý nhân bản logic để Trạm cân vẽ được bảng
 * mà không phải đi mạng — nếu chúng lệch nhau thì thợ nhìn thấy một đằng, DB ghi một nẻo.
 *
 *   node frontend/scripts/check-qr-parser.mjs
 *
 * Không chạm DB, không gọi API: nạp thẳng QrPayloadService qua `php -r`.
 */
import { execFileSync } from 'node:child_process';
import { build } from 'esbuild';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(here, '..', '..');

const CASES = [
  ['9 dòng đầy đủ', '#TESTQR-TC001-VD10-220-R1-DYE001-12.50-R2-DYE002-8.30-R3-DYE003-25.00-R4-DYE004-3.75-R5-DYE005-18.20-R6-DYE006-6.40-R7-DYE007-31.10-R8-DYE008-2.05-R9-DYE009-14.90'],
  ['3 dòng', '#TESTQR-TC195904-VD10-220-R1-DYE001-12.50-R2-DYE002-8.30-R3-DYE003-25.00'],
  ['không có dấu # đầu', 'RED-P123-VD10-220-R1-DYE001-1.500'],
  ['dấu phẩy thay dấu chấm', '#RED-P123-VD10-220-R1-DYE001-1,500'],
  ['có cụm -dye- xen giữa', '#RED-P123-VD10-220-dye-R1-DYE001-1.500'],
  ['cắt bỏ phần chem', '#RED-P123-VD10-220-R1-DYE001-1.500-chem-CH01-2.5'],
  ['thiếu bộ ba cuối', '#RED-P123-VD10-220-R1-DYE001'],
  ['chỉ có 4 phần đầu', '#RED-P123-VD10-220'],
  ['nhiều hơn 9 bộ ba (phải cắt còn 9)', '#C-P-M-L' + Array.from({ length: 11 }, (_, i) => `-R${i}-D${i}-${i}.5`).join('')],
  ['dấu gạch lặp', '#RED--P123---VD10-220-R1-DYE001-1.500'],
  ['chuỗi rỗng', ''],
  ['chỉ có #', '#'],
  ['chữ thường trong -DYE-', '#RED-P123-VD10-220-DyE-R1-D1-1.0'],
];

// --- Bản trình duyệt: transpile TS -> JS rồi import ---
const outDir = mkdtempSync(join(tmpdir(), 'qrparser-'));
const outFile = join(outDir, 'parser.mjs');
await build({
  entryPoints: [join(repoRoot, 'frontend', 'src', 'utils', 'qrDyeParser.ts')],
  outfile: outFile,
  format: 'esm',
  bundle: false,
  logLevel: 'silent',
});
const { parseDyeQr } = await import(pathToFileURL(outFile).href);

// --- Bản server: gọi thẳng QrPayloadService ---
const phpScript = `
require '${repoRoot.replace(/\\/g, '/')}/backend/vendor/autoload.php';
$app = require '${repoRoot.replace(/\\/g, '/')}/backend/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$svc = app(App\\Services\\QrPayloadService::class);
$out = [];
foreach (json_decode(file_get_contents('php://stdin'), true) as $raw) {
    $out[] = $svc->parseDyeScan($raw);
}
echo json_encode($out);
`;

const inputs = CASES.map(([, raw]) => raw);
const phpOut = execFileSync('php', ['-r', phpScript], {
  input: JSON.stringify(inputs),
  encoding: 'utf8',
  maxBuffer: 10 * 1024 * 1024,
});
const phpResults = JSON.parse(phpOut);

// --- So sánh ---
let pass = 0;
let fail = 0;
CASES.forEach(([ten, raw], i) => {
  const js = parseDyeQr(raw);
  const php = phpResults[i];
  const chuanHoa = (o) => JSON.stringify({
    color: o.color,
    code: o.code,
    machine: o.machine,
    level: o.level,
    rack_lines: o.rack_lines,
  });

  if (chuanHoa(js) === chuanHoa(php)) {
    pass++;
    console.log(`  PASS  ${ten}`);
  } else {
    fail++;
    console.log(`  FAIL  ${ten}`);
    console.log(`        JS : ${chuanHoa(js)}`);
    console.log(`        PHP: ${chuanHoa(php)}`);
  }
});

// Khôi phục sau F5 dựng lại bảng bằng cách parse LẠI chuỗi QR đã lưu, nên hàm phải thuần tuý:
// cùng đầu vào luôn cho cùng đầu ra. Nếu không, F5 xong thợ có thể thấy bảng khác lúc quét.
let idempotentOk = true;
for (const [, raw] of CASES) {
  if (JSON.stringify(parseDyeQr(raw)) !== JSON.stringify(parseDyeQr(raw))) {
    idempotentOk = false;
    console.log(`  FAIL  parse khong on dinh voi: ${raw}`);
  }
}
if (idempotentOk) {
  pass++;
  console.log('  PASS  parse lai cung chuoi luon cho cung ket qua (can cho khoi phuc sau F5)');
} else {
  fail++;
}

rmSync(outDir, { recursive: true, force: true });
console.log(`\nKet qua: ${pass} pass, ${fail} fail`);
process.exit(fail === 0 ? 0 : 1);
