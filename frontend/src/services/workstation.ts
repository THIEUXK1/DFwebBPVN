import { ref } from 'vue';
import axios from 'axios';

export interface Workstation {
  id: number;
  code: string;
  name: string;
  type: string;
  workstation_type?: string;
  location: string | null;
  active?: boolean;
  assigned_scale_device_id: string | null;
  assigned_printer_device_id: string | null;
  configuration?: any;
  default_screen?: string | null;
  default_route?: string | null;
  allowed_actions?: string[] | null;
  capability_codes?: string[];
  locked_to_type?: boolean;
}

/**
 * Capability bắt buộc cho từng route vận hành — nguồn dùng chung cho cả AppLayout.vue
 * (chặn hiển thị khi trạm đang chọn không có quyền cho màn hình) và router/index.ts
 * (giới hạn route cho phiên kiosk). Trước đây 2 nơi tự định nghĩa map riêng, từng lệch
 * nhau (CHEMICAL_CALL bị trỏ nhầm route feeding-monitor, phát hiện 2026-07-18).
 */
export const ROUTE_CAPABILITY_MAP: Record<string, string[]> = {
  '/print-station': ['QR_LABEL_PRINTING'],
  '/production-batches': ['PRODUCTION_ORDER'],
  '/weighing-station': ['SMALL_SCALE', 'LARGE_SCALE'],
  // Bản dựng lại — cùng ràng buộc capability với bản cũ để không lọt dữ liệu sang trạm
  // không phải trạm cân.
  '/weighing-station-v2': ['SMALL_SCALE', 'LARGE_SCALE'],
  '/chemical-call': ['CHEMICAL_CALL'],
};

export function workstationHasCapability(ws: Workstation | { capabilities?: any[] } | null, code: string): boolean {
  if (!ws) return false;
  const raw = (ws as any).capability_codes ?? (ws as any).capabilities;
  if (!raw) return false;
  return raw.some((c: any) => (typeof c === 'string' ? c === code : c.code === code && c.enabled !== false));
}

/**
 * true nếu route KHÔNG yêu cầu capability cụ thể, hoặc trạm có ít nhất 1 capability
 * phù hợp. Dùng để chặn hiển thị âm thầm dữ liệu/nhãn của đúng trạm nhưng sai màn hình
 * (vd: mở /print-station trong khi trạm đang chọn toàn cục vẫn là WS-ORDER-01 do
 * người dùng vừa rời màn hình Lô sản xuất).
 */
export function workstationMatchesRoute(ws: Workstation | null, path: string): boolean {
  const required = ROUTE_CAPABILITY_MAP[path];
  if (!required) return true;
  return required.some(code => workstationHasCapability(ws, code));
}

const STORAGE_KEY = 'df_current_workstation';

export const currentWorkstation = ref<Workstation | null>(null);
export const workstationsList = ref<Workstation[]>([]);
export const loadingWorkstations = ref<boolean>(false);

// Initialize from LocalStorage, checking handshake config first
const saved = localStorage.getItem('df_workstation_config') || localStorage.getItem(STORAGE_KEY);
if (saved) {
  try {
    currentWorkstation.value = JSON.parse(saved);
  } catch (err) {
    localStorage.removeItem(STORAGE_KEY);
  }
}

/**
 * Fetch active workstations list from backend.
 */
export async function fetchWorkstations() {
  loadingWorkstations.value = true;
  try {
    const res = await axios.get('/api/workstations');
    if (res.data?.status === 'SUCCESS') {
      workstationsList.value = res.data.data || [];
    }
  } catch (err) {
    console.error('Failed to fetch workstations list:', err);
  } finally {
    loadingWorkstations.value = false;
  }
}

/**
 * Assign the current client to a specific workstation.
 */
export function setWorkstation(ws: Workstation | null) {
  currentWorkstation.value = ws;
  if (ws) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(ws));
  } else {
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem('df_workstation_config');
    localStorage.removeItem('df_workstation_token');
  }
}

/**
 * Tự nhận trạm của CHÍNH máy này: hỏi backend "máy tôi đang ngồi là trạm nào?", backend tra
 * theo IP nguồn ra trạm mà Agent chạy trên máy này đã tự đăng ký.
 *
 * Nhờ vậy cài bộ MSI y hệt nhau lên bao nhiêu máy cũng xong — mỗi máy tự có trạm riêng, không
 * phải khai tay trong Quản lý Workstation cũng không phải chọn trong danh sách mỗi lần mở máy.
 *
 * KHÔNG đụng tới tài khoản/phiên kiosk đã gắn cứng trạm (`df_workstation_config`): ở đó trạm là
 * do quản trị chỉ định có chủ đích, đè lên là phá đúng thứ WS-001 dựng ra để chặn chọn nhầm.
 * Trả về true nếu đã đổi sang trạm của máy.
 */
export async function adoptLocalWorkstation(): Promise<boolean> {
  if (localStorage.getItem('df_workstation_config')) return false;

  try {
    const res = await axios.get('/api/workstations/whoami');
    const ws = res.data?.data;
    // data=null là trạng thái BÌNH THƯỜNG (máy chưa cài Agent, hoặc PuTTY chưa bật nên Agent
    // chưa đẩy số nào) — giữ nguyên trạm đang chọn, không báo lỗi.
    if (!ws?.id) return false;
    if (currentWorkstation.value?.id === ws.id) return false;

    setWorkstation(ws);

    return true;
  } catch {
    // Backend cũ chưa có endpoint này -> 404. Không phải lỗi người dùng cần biết.
    return false;
  }
}

/**
 * Check if the current workstation has access to a specific action type.
 */
export function canPerform(action: string): boolean {
  if (!currentWorkstation.value) return false;
  const actions = currentWorkstation.value.allowed_actions || [];
  return actions.includes(action);
}
