/**
 * Row lock của các lưới VBA gốc (Mod_loadgrid.LoadGrid_safe trong workbook C3,
 * TO_SEND.LoadGrid trong workbook DF002 — cả hai dùng y hệt một thuật toán).
 *
 * Mỗi ô lưới giữ chặt id của bản ghi đang chiếm nó (chính là `.Tag` của control ID<n>
 * bên VBA). Nhờ vậy dòng KHÔNG BAO GIỜ nhảy chỗ giữa hai lần làm mới: xử lý xong một
 * đơn chỉ để lại một ô trống, các dòng phía dưới không dồn lên. Đây là chốt an toàn
 * thật sự chứ không phải thẩm mỹ — lưới tự làm mới mỗi 15s, nếu dòng nhảy chỗ thì người
 * vận hành đang định bấm nút ở dòng 5 có thể bấm trúng đơn khác sau một nhịp làm mới.
 */
export function applyVbaRowLock<T extends { id: string }>(
  currentTags: (string | null)[],
  rows: T[],
  slotCount: number
): { tags: (string | null)[]; byId: Map<string, T> } {
  const byId = new Map<string, T>(rows.map(r => [r.id, r]));
  const tags = [...currentTags];
  const placed = new Set<string>();

  // A + C: giữ nguyên ô cho id còn tồn tại; giải phóng ô của id đã rời hàng chờ.
  for (let i = 0; i < slotCount; i++) {
    const tag = tags[i];
    if (!tag) continue;
    if (byId.has(tag)) placed.add(tag);
    else tags[i] = null;
  }

  // B: id mới -> ô trống đầu tiên (quét từ trên xuống, đúng vòng lặp VBA).
  for (const r of rows) {
    if (placed.has(r.id)) continue;
    const free = tags.indexOf(null);
    if (free === -1) break; // hết ô — VBA cũng bỏ qua phần dư
    tags[free] = r.id;
    placed.add(r.id);
  }

  return { tags, byId };
}

/**
 * Mod_load_input.AgeColor_ID — tô nền ô ID theo tuổi đơn để nhìn phát biết đơn tồn lâu.
 * > 48h: đỏ đậm | >= 24h: tím đậm | < 24h: xanh đậm.
 */
export function vbaAgeColor(createdAt: string | null | undefined): string {
  if (!createdAt) return '#ffffff';
  const hours = (Date.now() - new Date(createdAt).getTime()) / 3_600_000;
  if (hours > 48) return 'rgb(180,0,0)';
  if (hours >= 24) return 'rgb(150,0,150)';
  return 'rgb(0,180,0)';
}
