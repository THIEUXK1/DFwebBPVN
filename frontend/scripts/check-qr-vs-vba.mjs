/**
 * Doi chieu cong nhan ma QR cua WEB voi `txt_color_AfterUpdate` cua VBA.
 *
 * Nguon VBA: workbook can to "5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm",
 * module `scaleform` dong 427-496 + `Mod_delta_raw.CleanLeadingGarbage` dong 243-261 (trich thang
 * tu xl/vbaProject.bin, 07/08/2026).
 *
 * VI SAO CAN SCRIPT NAY, da co check-qr-parser.mjs roi:
 * check-qr-parser.mjs chi so JS voi PHP — hai ban NHA MINH viet. Ca hai cung sai theo mot kieu
 * thi no van xanh. Va do dung la chuyen da xay ra: ca hai deu chi bo dau "#" trong khi VBA bo MOI
 * ky tu truoc chu/so dau tien, nen con tem thieu "#" bi web tu choi trong khi form VBA nap binh
 * thuong. Script nay so voi BAN GOC, la thu duy nhat bat duoc loai loi do.
 *
 * Chay: node scripts/check-qr-vs-vba.mjs   (tu thu muc frontend)
 */
import { build } from 'esbuild';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const tmp = mkdtempSync(join(tmpdir(), 'qrchk-'));
await build({
  entryPoints: [new URL('../src/utils/qrDyeParser.ts', import.meta.url).pathname.replace(/^\//, '')],
  bundle: true, format: 'esm', outfile: join(tmp, 'p.mjs'), logLevel: 'silent',
});
const { docQrMeNhuom } = await import(pathToFileURL(join(tmp, 'p.mjs')));

/* ---- Ban VBA, port nguyen van tu scaleform.bas dong 427-496 + Mod_delta_raw.bas 243-261 ---- */

// CleanLeadingGarbage: bo MOI ky tu dau chuoi cho toi ky tu chu-hoac-so DAU TIEN.
function cleanLeadingGarbage(s) {
  s = s.trim();
  for (let i = 0; i < s.length; i++) {
    if (/[A-Za-z0-9]/.test(s[i])) return s.slice(i);
  }
  return s;
}

function vbaParse(raw) {
  let s = cleanLeadingGarbage(String(raw).trim());
  s = s.split(',').join('.');
  s = s.split('-dye-').join('-');              // VBA Replace: PHAN BIET hoa thuong
  const chem = s.toLowerCase().indexOf('chem');
  if (chem !== -1) s = s.slice(0, chem);
  if (s === '') return null;                    // GoTo SafeExit — form giu nguyen

  const parts2 = s.split('-').map((x) => x.trim()).filter((x) => x !== '');
  if (parts2.length === 0) return null;

  const rack = [];
  let idx = 4;
  for (let i = 0; i < 9; i++) {
    if (idx > parts2.length - 1) break;
    const r = { rack: parts2[idx++], dye: '', weight: '' };
    if (idx <= parts2.length - 1) r.dye = parts2[idx++];
    if (idx <= parts2.length - 1) r.weight = parts2[idx++];
    rack.push(r);
    if (idx > parts2.length - 1) break;
  }
  return { color: parts2[0] ?? '', code: parts2[1] ?? '', machine: parts2[2] ?? '',
           level: parts2[3] ?? '', rack_lines: rack };
}

/* ---- Chot cua WEB: goi THANG ham that ma 3 man can dung, khong chep lai logic ---- */
function webNhan(raw) {
  const p = docQrMeNhuom(String(raw).trim());
  return p ? { nhan: true, p } : { nhan: false, vi: '"Khong doc duoc ma QR nay"' };
}

const CA = [
  ['QR chuan',                    '#RED01-N20-VD006-450-1-A01-12.5-2-A02-4.25'],
  ['thieu "#" o dau',             'RED01-N20-VD006-450-1-A01-12.5'],
  ['co dau cach truoc "#"',       '  #RED01-N20-VD006-450-1-A01-12.5'],
  ['may quet them ky tu la',      '*#RED01-N20-VD006-450-1-A01-12.5'],
  ['xuong dong o dau',            '\r\n#RED01-N20-VD006-450-1-A01-12.5'],
  ['"#" bi go nham thanh "\\"',   '\\RED01-N20-VD006-450-1-A01-12.5'],
  ['hai dau "##"',                '##RED01-N20-VD006-450-1-A01-12.5'],
  ['co cum -dye-',                '#RED01-N20-VD006-450-dye-1-A01-12.5'],
  ['dung dau phay thap phan',     '#RED01-N20-VD006-450-1-A01-12,5'],
  ['bo ba cuoi thieu weight',     '#RED01-N20-VD006-450-1-A01'],
  ['chi co dong 1 rack',          '#RED01-N20-VD006-450-1'],
  ['chi co 4 o header',           '#RED01-N20-VD006-450'],
  ['co duoi chem',                '#RED01-N20-VD006-450-1-A01-12.5-chem-C_SALT-150'],
];

let lech = 0;
console.log('KQ  | truong hop                     | VBA | WEB');
console.log('----+--------------------------------+-----+---------------------------------------');
for (const [ten, raw] of CA) {
  const v = vbaParse(raw);
  const w = webNhan(raw);
  const vbaNhan = v !== null && v.color !== '';
  const khop = vbaNhan === w.nhan;
  if (!khop) lech++;
  console.log(
    `${khop ? ' ok ' : 'LECH'}| ${ten.padEnd(30)} | ${vbaNhan ? ' co ' : 'kh. '} | ${w.nhan ? 'co' : 'KHONG — ' + w.vi}`
  );
}
console.log(`\n=> ${lech}/${CA.length} truong hop VBA va WEB xu ly KHAC NHAU.`);
rmSync(tmp, { recursive: true, force: true });
process.exit(lech === 0 ? 0 : 1);
