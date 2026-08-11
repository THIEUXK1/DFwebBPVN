// Ghi chú dịch: các caption "ORDER" / "DONE" / "OK" trong ChemicalCallClassic.vue là chữ
// gốc lấy nguyên văn từ UserForm CHEM_ORDER trong workbook VBA (giao diện Windows cổ điển
// tái tạo y hệt bản gốc) — CỐ Ý không đưa vào $t()/module này, xem báo cáo cuối phiên.
export default {
  vi: {
    loadingLabel: 'Đang tải bảng gọi hóa chất...',
    callButtonTitle: 'Gọi hóa chất {code} cho {machine}',
    okButtonTitle: 'Xác nhận đã cấp xong',
    errorFetchChannels: 'Không thể kết nối đến máy chủ API để lấy thông tin van đường ống.',
    errorCallChemical: 'Không thể gọi hóa chất cho thùng này.',
    errorMarkDone: 'Không thể xác nhận xong cho thùng này.',
  },
  en: {
    loadingLabel: 'Loading chemical call board...',
    callButtonTitle: 'Call chemical {code} for {machine}',
    okButtonTitle: 'Confirm dosing complete',
    errorFetchChannels: 'Unable to connect to the API server to retrieve pipeline valve information.',
    errorCallChemical: 'Unable to call chemical for this tank.',
    errorMarkDone: 'Unable to confirm completion for this tank.',
  },
  zh: {
    loadingLabel: '正在加载化料呼叫看板...',
    callButtonTitle: '为 {machine} 呼叫化料 {code}',
    okButtonTitle: '确认投料完成',
    errorFetchChannels: '无法连接到API服务器以获取管道阀门信息。',
    errorCallChemical: '无法为该料桶呼叫化料。',
    errorMarkDone: '无法确认该料桶已完成。',
  },
};
