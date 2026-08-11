// Ghi chú dịch: caption "OK" trong ChemicalCallPendingClassic.vue là chữ gốc lấy nguyên văn
// từ UserForm CHEM_ORDER trong workbook VBA (giao diện Windows cổ điển tái tạo y hệt bản
// gốc) — CỐ Ý không đưa vào $t()/module này, xem báo cáo cuối phiên.
export default {
  vi: {
    loadingLabel: 'Đang tải danh sách đang chờ xử lý...',
    okButtonTitle: 'Xác nhận đã cấp xong',
    overflowHint: '+{count} khác đang chờ — xem đầy đủ ở "Đang chờ xử lý"',
    errorFetchChannels: 'Không thể kết nối đến máy chủ API để lấy thông tin van đường ống.',
    errorMarkDone: 'Không thể xác nhận xong cho thùng này.',
  },
  en: {
    loadingLabel: 'Loading pending list...',
    okButtonTitle: 'Confirm dosing complete',
    overflowHint: '+{count} more pending — see full list in "Pending"',
    errorFetchChannels: 'Unable to connect to the API server to retrieve pipeline valve information.',
    errorMarkDone: 'Unable to confirm completion for this tank.',
  },
  zh: {
    loadingLabel: '正在加载待处理列表...',
    okButtonTitle: '确认投料完成',
    overflowHint: '还有 {count} 项待处理 — 请到"待处理"查看完整列表',
    errorFetchChannels: '无法连接到API服务器以获取管道阀门信息。',
    errorMarkDone: '无法确认该料桶已完成。',
  },
};
