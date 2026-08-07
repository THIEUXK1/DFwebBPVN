import { ref } from 'vue';

// Trạng thái Toàn màn hình (ẩn sidebar+topbar) dùng chung toàn app — cùng pattern với
// services/theme.ts. Các trang con (vd ChemicalCall.vue) đọc giá trị này để tự co giãn
// layout riêng (vd lưới máy dùng thêm cột khi có nhiều chỗ trống hơn lúc Toàn màn hình).
export const isFullscreen = ref(false);

/**
 * Lựa chọn "thanh trên cùng (topbar) hiện hay ẩn" của các tài khoản đứng trạm cân
 * (cannho / canto — yêu cầu 08/08/2026). Hai màn cân chiếm gần trọn màn hình nên 70px
 * topbar là 70px không dùng tới; mặc định của các tài khoản đó là ẩn, chỉ chừa một dải
 * mỏng có nút bật lại (xem AppLayout.vue).
 *
 * KHÁC `isFullscreen` ở chỗ đây là lựa chọn LÂU DÀI của máy trạm (nhớ qua F5, qua phiên),
 * còn `isFullscreen` là trạng thái tức thời của phiên xem hiện tại và bị các trang tự đặt
 * lại khi mount/unmount.
 *
 * `null` = chưa ai bấm gì -> dùng mặc định theo loại tài khoản. Chỉ có ý nghĩa với tài
 * khoản trạm cân; tài khoản khác luôn thấy topbar như cũ.
 */
export type TopbarPref = 'show' | 'hide';

const TOPBAR_PREF_KEY = 'df_topbar_pref';

const savedTopbarPref = localStorage.getItem(TOPBAR_PREF_KEY);
export const topbarPref = ref<TopbarPref | null>(
  savedTopbarPref === 'show' || savedTopbarPref === 'hide' ? savedTopbarPref : null
);

export function setTopbarPref(pref: TopbarPref) {
  topbarPref.value = pref;
  localStorage.setItem(TOPBAR_PREF_KEY, pref);
}
