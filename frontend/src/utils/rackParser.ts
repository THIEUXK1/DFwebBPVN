// Tách chuỗi phẳng "rack-mã-khối lượng-rack-mã-khối lượng-..." (raw_qr_dye / raw_qr_chemical,
// đúng định dạng VBA gốc — xem QrPayloadService::parseOrderEntryScan phía backend) thành từng
// dòng RACK/MÃ/KHỐI LƯỢNG để hiển thị dạng bảng giống scaleform.frm. Dùng chung cho
// ProductionBatches.vue (xem trước khi lưu) và PrintStation.vue (xem trước khi in).
export interface RackLine {
  rack: string;
  code: string;
  weight: string;
}

export function parseRackLines(raw: string | null | undefined): RackLine[] {
  if (!raw) return [];
  const parts = raw.split('-').map(p => p.trim()).filter(p => p !== '');
  const lines: RackLine[] = [];
  for (let i = 0; i < parts.length; i += 3) {
    lines.push({ rack: parts[i] ?? '', code: parts[i + 1] ?? '', weight: parts[i + 2] ?? '' });
  }
  return lines;
}
