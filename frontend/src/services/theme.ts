import { ref } from 'vue';

export type ThemeMode = 'dark' | 'light';

const STORAGE_KEY = 'df_theme';

function getInitialTheme(): ThemeMode {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved === 'light' || saved === 'dark') return saved;
  // Mặc định giao diện sáng.
  return 'light';
}

export const theme = ref<ThemeMode>(getInitialTheme());

function applyTheme(mode: ThemeMode) {
  document.documentElement.setAttribute('data-theme', mode);
}

/** Gọi 1 lần lúc app khởi động (main.ts), trước khi mount, để tránh nháy sai theme. */
export function initTheme() {
  applyTheme(theme.value);
}

export function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem(STORAGE_KEY, theme.value);
  applyTheme(theme.value);
}
