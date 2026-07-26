import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import legacy from '@vitejs/plugin-legacy'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // Chỉ áp dụng cho bản build production (npm run build), không ảnh hưởng `npm run dev`.
    // Sinh thêm 1 bundle dự phòng (kèm polyfill core-js) cho trình duyệt cũ không hỗ trợ
    // native ES module — dùng cho máy Kiosk đời cũ ở xưởng bị crash STATUS_ILLEGAL_INSTRUCTION
    // khi vào bản dev (2026-07-25).
    legacy({
      targets: ['defaults', 'not IE 11', 'chrome >= 49', 'edge >= 14', 'safari >= 10'],
    }),
  ],
  build: {
    target: 'es2015',
  },
  server: {
    port: 3001,
    host: true
  }
})
