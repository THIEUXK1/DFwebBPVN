import { createI18n } from 'vue-i18n';

type LocaleModule = { vi: Record<string, unknown>; en: Record<string, unknown>; zh: Record<string, unknown> };

// Mỗi view/component nghiệp vụ có 1 module riêng dưới src/locales/modules/<Ten>.ts, export
// default { vi, en, zh }. Namespace PHẢI trùng tên key dùng trong $t('<namespace>.xxx') ở
// template/script của view đó. Tách file theo view để nhiều người/agent sửa song song không
// đụng nhau, khác với việc dồn tất cả vào 1 file JSON khổng lồ.
//
// vi luôn là bản gốc y nguyên chữ đang hiển thị trên UI — không "dịch" tiếng Việt, giữ đúng
// nguyên văn cũ để bật ngôn ngữ VI không đổi gì so với trước khi có i18n.
//
// Quét bằng import.meta.glob thay vì liệt kê tay từng import: liệt kê tay thì thêm file mới
// mà quên đăng ký sẽ KHÔNG lỗi build, chỉ lòi ra chuỗi key thô ("materials.title") trên màn
// hình chạy thật ở xưởng. eager:true là bắt buộc — messages phải có đủ NGAY lúc createI18n.
// Namespace suy từ tên file, viết thường chữ đầu: Materials.ts -> materials.
const modules: Record<string, LocaleModule> = {};
const files = import.meta.glob<{ default: LocaleModule }>('../locales/modules/*.ts', { eager: true });
for (const [path, mod] of Object.entries(files)) {
  const fileName = path.split('/').pop()!.replace(/\.ts$/, '');
  modules[fileName.charAt(0).toLowerCase() + fileName.slice(1)] = mod.default;
}

function buildMessages() {
  const messages: Record<string, Record<string, unknown>> = { vi: {}, en: {}, zh: {} };
  for (const [namespace, mod] of Object.entries(modules)) {
    messages.vi[namespace] = mod.vi;
    messages.en[namespace] = mod.en;
    messages.zh[namespace] = mod.zh;
  }
  return messages;
}

export type AppLocale = 'vi' | 'en' | 'zh';

const STORAGE_KEY = 'df_locale';

function getInitialLocale(): AppLocale {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved === 'vi' || saved === 'en' || saved === 'zh') return saved;
  // Mặc định tiếng Việt — ngôn ngữ gốc của toàn bộ nghiệp vụ, không cần dịch gì.
  return 'vi';
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: getInitialLocale(),
  fallbackLocale: 'vi',
  messages: buildMessages(),
});

export function setLocale(loc: AppLocale) {
  i18n.global.locale.value = loc;
  localStorage.setItem(STORAGE_KEY, loc);
  document.documentElement.setAttribute('lang', loc);
}

/** Gọi 1 lần lúc app khởi động (main.ts) để gắn thuộc tính lang= ban đầu cho <html>. */
export function initLocale() {
  document.documentElement.setAttribute('lang', i18n.global.locale.value);
}
