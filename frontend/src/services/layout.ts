import { ref } from 'vue';

// Trạng thái Toàn màn hình (ẩn sidebar+topbar) dùng chung toàn app — cùng pattern với
// services/theme.ts. Các trang con (vd ChemicalCall.vue) đọc giá trị này để tự co giãn
// layout riêng (vd lưới máy dùng thêm cột khi có nhiều chỗ trống hơn lúc Toàn màn hình).
export const isFullscreen = ref(false);

/**
 * Thanh trên cùng (topbar) đang hiện hay ẩn, cho các tài khoản đứng trạm cân (cannho / canto).
 * Hai màn cân chiếm gần trọn màn hình nên 70px topbar là 70px không dùng tới; mặc định của các
 * tài khoản đó là ẩn, chỉ chừa một dải mỏng có nút bật lại (xem AppLayout.vue).
 *
 * KHÔNG GHI NHỚ, và đó là chủ ý (yêu cầu 08/08/2026: "auto được ẩn, hiện thì F5 lại ẩn"). Bản
 * đầu (08/08/2026 sáng) lưu vào localStorage `df_topbar_pref`; hệ quả là thợ bấm "▾ Thanh trên"
 * một lần để xem tên trạm rồi quên bấm lại, và máy đó mất 70px vĩnh viễn cho tới khi có người
 * nhớ ra. Nay để trong bộ nhớ phiên: bật lên xem xong, F5 là tự về nếp ẩn.
 *
 * Khoá `df_topbar_pref` cũ trên các máy đã chạy bản trước nay không còn ai đọc — để đó vô hại.
 *
 * KHÁC `isFullscreen` ở chỗ `isFullscreen` bị chính các trang tự đặt lại khi mount/unmount, còn
 * cờ này chỉ đổi khi thợ bấm nút.
 *
 * `null` = chưa ai bấm gì -> dùng mặc định theo loại tài khoản. Chỉ có ý nghĩa với tài khoản
 * trạm cân; tài khoản khác luôn thấy topbar như cũ.
 */
export type TopbarPref = 'show' | 'hide';

export const topbarPref = ref<TopbarPref | null>(null);

export function setTopbarPref(pref: TopbarPref) {
  topbarPref.value = pref;
}
