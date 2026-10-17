// frontend/src/services/scaleSource.ts
//
// Công tắc NGUỒN CÂN (24/09/2026): 1 = PuTTY truyền thống (Agent đọc file log PuTTY), 2 = Agent tự
// dò cổng COM đang có cân rồi đọc thẳng cổng đó.
//
// Vì sao là state dùng chung chứ không nằm trong useScaleFeed: công tắc đứng trên THANH TRÊN của
// AppLayout (yêu cầu người dùng), còn số cân — và các trường nguồn cân đi kèm trong gói số — thì do
// màn trạm cân nhận. Màn trạm cân đang mở ghi vào đây, thanh trên đọc ra; không có màn trạm cân nào
// mở (`kind === null`) thì thanh trên ẩn công tắc.

import { reactive } from 'vue';

/**
 * Loại cân mà màn hình đang dùng — cũng chính là loại BỘ CÀI Agent phục vụ nó:
 *   SMALL → Agent "Cân nhỏ" (service DFAgentSmall, mã trạm WS-SCALE-*), màn /weighing-station-v2
 *   LARGE → Agent "Cân to"  (service DFAgentLarge, mã trạm WS-LARGE-*), màn /weighing-station-large
 *
 * Hai bộ cài chạy song song được trên CÙNG một máy, mà backend ghép cặp trình duyệt với Agent
 * theo IP nguồn — nên nếu không nói rõ đang hỏi cân nào thì màn Cân to có thể đọc phải số của
 * cân nhỏ. Tham số này chính là thứ tách hai đường đó ra.
 */
export type ScaleKind = 'SMALL' | 'LARGE';

/**
 * ĐƯỜNG CÂN CỤC BỘ (ADR-013) — cổng mà Agent trên CHÍNH máy trạm này đang nghe.
 *
 * Con số cổng là QUY ƯỚC hai bên tự biết: trang web không có cách nào đọc `appsettings.json` của
 * Agent. Đổi ở đây thì phải đổi `LocalWeightServer.CongMacDinh*` bên `agent/LocalWeightServer.cs`.
 * Hai loại cân hai cổng vì hai bộ cài chạy song song được trên cùng một máy.
 */
export const CONG_AGENT_CUC_BO: Record<ScaleKind, number> = { SMALL: 8770, LARGE: 8771 };

export type CheDoNguonCan = 'PUTTY_LOG' | 'AUTO_COM';

export const nguonCan = reactive<{
  /** Màn trạm cân đang mở dùng cân nào. null = không có màn trạm cân nào → ẩn công tắc. */
  kind: ScaleKind | null;
  /** 'PUTTY_LOG' | 'AUTO_COM' | 'SERIAL'; null = không rõ (đi đường backend / Agent đời cũ). */
  mode: string | null;
  /** Cổng COM Agent đang đọc (chỉ có ở AUTO_COM/SERIAL). */
  port: string | null;
  /** Một câu Agent tự mô tả tình trạng nguồn cân — kết quả dò cổng, file PuTTY đang đọc... */
  msg: string | null;
  /** Đang cân dở (đã bấm TIẾP): đổi nguồn giữa mẻ là đổi cái cân mà bì đang chốt theo. */
  khoa: boolean;
}>({ kind: null, mode: null, port: null, msg: null, khoa: false });

/** Ghi các trường nguồn cân từ một gói số cân — chỉ gói của Agent cục bộ mới có chúng. */
export function ghiNguonTuGoiSo(kind: ScaleKind, d: any) {
  if (nguonCan.kind !== kind) return;
  nguonCan.mode = d?.scale_mode ?? null;
  nguonCan.port = d?.com_port ?? null;
  nguonCan.msg = d?.scale_msg ?? null;
}

/**
 * Bảo Agent trên CHÍNH máy này chuyển nguồn cân. Đi thẳng loopback (không qua backend, không gửi
 * token đăng nhập) — trình duyệt vẫn không chạm phần cứng, việc mở cổng COM là của Agent (ADR-002).
 *
 * `doLai` = nút DÒ CÂN: Agent bỏ cổng COM đã cố định và dò lại từ đầu. Không có cờ này thì chế
 * độ COM mở thẳng cổng đã cố định, không dò — dò mất vài giây mỗi cổng.
 *
 * Ném Error với mã ngắn để giao diện dịch ra câu dễ hiểu.
 */
export async function doiNguonCan(mode: CheDoNguonCan, doLai = false): Promise<void> {
  const kind = nguonCan.kind;
  if (!kind) throw new Error('NO_STATION');

  const huy = new AbortController();
  const hetGio = setTimeout(() => huy.abort(), 3000);
  let res: Response;
  try {
    res = await fetch(`http://127.0.0.1:${CONG_AGENT_CUC_BO[kind]}/scale/source`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mode, rescan: doLai }),
      cache: 'no-store',
      credentials: 'omit',
      signal: huy.signal,
    });
  } catch {
    throw new Error('AGENT_UNREACHABLE');
  } finally {
    clearTimeout(hetGio);
  }

  const d = await res.json().catch(() => null);
  // 404 = Agent đời cũ chưa có công tắc; 403 = mở màn hình bằng địa chỉ chưa khai trong
  // Local:AllowedOriginHosts của Agent.
  if (!res.ok) throw new Error(res.status === 404 ? 'AGENT_TOO_OLD' : res.status === 403 ? 'ORIGIN_FORBIDDEN' : 'AGENT_REJECTED');

  nguonCan.mode = d?.scale_mode ?? mode;
  nguonCan.port = d?.com_port ?? null;
  nguonCan.msg = d?.scale_msg ?? null;
}
