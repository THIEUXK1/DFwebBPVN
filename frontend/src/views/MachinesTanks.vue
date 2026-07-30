<template>
  <div class="dashboard-container">
    <main class="main-content">
      <section class="section card-sec">
        <!-- Banner mô phỏng đúng tiêu đề mainform VBA gốc (BPVN / DF PRODUCTION ORDER
             INFORMATION) — giữ nguyên màu cố định kiểu UserForm cũ, không đổi theo
             theme sáng/tối, vì mục đích là gợi lại đúng hình ảnh form gốc. -->
        <div class="vba-banner">
          <span class="vba-logo">BPVN</span>
          <span class="vba-title">DF PRODUCTION ORDER INFORMATION</span>
          <!-- Nút mở trang Quét đơn sản xuất — giống nút trên Sheet1 mở mainform trong
               VBA gốc (Mod_openMainform / Mod_mainform_showsafe.OpenForm_Safe). Trang
               này CHỈ hiển thị dữ liệu, không nhập/lưu trực tiếp — mọi thao tác quét/Save
               thật vẫn ở /production-batches, tránh trùng lặp logic quét nhạy cảm. -->
          <router-link to="/production-batches" class="vba-open-form-btn">
            📋 Mở Quét đơn sản xuất
          </router-link>
        </div>

        <div class="section-header mt-2">
          <p class="section-desc">
            Mỗi cột là 1 máy nhuộm (VD01–VD18...), các dòng bên dưới là thùng trộn (1A–4D) của máy
            đó — đúng bố cục lưới cột-theo-máy trong <code>mainform</code> VBA gốc. Trang này chỉ để
            xem, muốn nhập/lưu đơn mới bấm nút "Mở Quét đơn sản xuất" ở trên.
          </p>
          <div class="header-actions">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="🔍 Tìm mã máy..."
              class="form-input search-box"
            />
            <button class="refresh-btn" @click="fetchAll" :disabled="loading">
              {{ loading ? 'Đang tải...' : '🔄 Làm mới' }}
            </button>
            <button class="add-btn" @click="openAddModal">➕ Thêm máy mới</button>
          </div>
        </div>

        <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>

        <div class="age-legend">
          <span class="legend-item"><span class="legend-swatch swatch-fresh"></span>Có lệnh &lt; 24h</span>
          <span class="legend-item"><span class="legend-swatch swatch-warn"></span>Chờ gửi máy 24–48h</span>
          <span class="legend-item"><span class="legend-swatch swatch-stale"></span>Tồn đọng &gt; 48h</span>
          <span class="legend-item"><span class="legend-swatch swatch-none"></span>Không có lệnh đang chờ</span>
        </div>

        <div class="vd-grid-wrapper mt-2">
          <table class="vd-grid-table">
            <thead>
              <tr>
                <th
                  v-for="m in filteredMachines"
                  :key="m.id"
                  :class="dispatchAgeClass(m.id)"
                  :title="dispatchAgeTitle(m.id)"
                >
                  {{ m.code }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in maxTankRows" :key="i">
                <td v-for="m in filteredMachines" :key="m.id" class="tank-cell">
                  <template v-if="cellData[m.id]?.[i - 1]">
                    <div class="tank-code">{{ cellData[m.id][i - 1].tank.code }}</div>
                    <div
                      v-if="cellData[m.id][i - 1].dispatch"
                      class="tank-order"
                      :class="'state-' + cellData[m.id][i - 1].dispatch.queue_state"
                      :title="orderTooltip(cellData[m.id][i - 1].dispatch)"
                    >
                      <span class="order-line">
                        {{ cellData[m.id][i - 1].dispatch.batch?.color }} · {{ cellData[m.id][i - 1].dispatch.batch?.product_code }}
                      </span>
                      <span class="order-badge">{{ stateLabel(cellData[m.id][i - 1].dispatch.queue_state) }}</span>
                    </div>
                    <div v-else class="tank-idle">— trống —</div>
                  </template>
                </td>
              </tr>
              <tr v-if="maxTankRows === 0">
                <td v-for="m in filteredMachines" :key="m.id" class="empty-cell">—</td>
              </tr>
            </tbody>
          </table>

          <div v-if="!loading && filteredMachines.length === 0" class="empty-state">
            Không tìm thấy máy nào khớp "{{ searchQuery }}".
          </div>
        </div>

        <p class="source-footnote mt-2">
          📁 Nguồn dữ liệu gốc (VBA Access, tham khảo): <code>Z:\DF\data\record.accdb</code> · dự phòng
          qua mạng: <code>\\10.0.55.3\Share\DF\DATA</code>. Trang web này đọc dữ liệu thật qua API
          Postgres (<code>/api/machines</code>, <code>/api/tanks</code>, <code>/api/machine-dispatches</code>),
          không kết nối trực tiếp file Access.
        </p>
      </section>
    </main>

    <!-- Add Machine Modal -->
    <div class="modal-backdrop" v-if="showAddModal">
      <div class="modal-card">
        <div class="modal-header">
          <h4>➕ Thêm máy nhuộm mới</h4>
          <button @click="closeAddModal" class="close-modal-btn">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="new-machine-code">Mã máy (vd: VD19)</label>
            <input id="new-machine-code" v-model="addForm.code" type="text" class="form-input" />
          </div>
          <div class="form-group">
            <label for="new-machine-name">Tên máy</label>
            <input id="new-machine-name" v-model="addForm.name" type="text" class="form-input" />
          </div>
          <p v-if="addError" class="text-error font-xs">{{ addError }}</p>
        </div>
        <div class="modal-footer">
          <button @click="closeAddModal" class="cancel-btn">Hủy</button>
          <button @click="submitAddMachine" class="submit-btn" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu máy mới' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const authStore = useAuthStore();

const machines = ref<any[]>([]);
const tanks = ref<any[]>([]);
const dispatches = ref<any[]>([]);
const searchQuery = ref('');
const loading = ref(false);
const errorMsg = ref('');
let autoRefreshTimer: any = null;

const showAddModal = ref(false);
const addForm = ref({ code: '', name: '' });
const addError = ref('');
const saving = ref(false);

const authHeaders = () => ({ Authorization: `Bearer ${authStore.token}` });

const fetchMachines = async () => {
  const response = await axios.get('/api/machines', { headers: authHeaders() });
  machines.value = response.data.data || [];
};

const fetchTanks = async () => {
  const response = await axios.get('/api/tanks', { headers: authHeaders() });
  tanks.value = response.data.data || [];
};

// Hàng chờ điều phối đang PENDING (chưa gửi máy) — dùng để tô màu tuổi dữ liệu
// theo đúng ngưỡng mainform VBA gốc (LoadAllVD_Input: trắng <24h, xanh nhạt
// 24-48h, tím >48h tính từ TIME1 quét đơn). Nếu API lỗi (vd tài khoản không có
// quyền), bỏ qua tô màu — không chặn phần danh mục Máy/Thùng vẫn hiển thị được.
const fetchDispatches = async () => {
  try {
    const response = await axios.get('/api/machine-dispatches', { headers: authHeaders() });
    dispatches.value = response.data || [];
  } catch (error) {
    dispatches.value = [];
  }
};

const fetchAll = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    await Promise.all([fetchMachines(), fetchTanks(), fetchDispatches()]);
  } catch (error) {
    console.error('Fetch machines/tanks error:', error);
    errorMsg.value = 'Không tải được danh mục Máy/Thùng trộn.';
  } finally {
    loading.value = false;
  }
};

const tanksByMachine = computed(() => {
  const map: Record<number, any[]> = {};
  for (const t of tanks.value) {
    if (!map[t.machine_id]) map[t.machine_id] = [];
    map[t.machine_id].push(t);
  }
  return map;
});

const filteredMachines = computed(() => {
  const q = searchQuery.value.trim().toUpperCase();
  if (!q) return machines.value;
  return machines.value.filter(m => m.code.toUpperCase().includes(q));
});

const maxTankRows = computed(() => {
  let max = 0;
  for (const m of filteredMachines.value) {
    const count = (tanksByMachine.value[m.id] || []).length;
    if (count > max) max = count;
  }
  return max;
});

// Lệnh chờ CŨ NHẤT của mỗi máy — tồn đọng càng lâu càng đáng chú ý (giống lý do
// mainform tô màu cảnh báo theo tuổi dữ liệu, không phải theo lệnh mới nhất).
const oldestPendingByMachine = computed(() => {
  const map: Record<number, string> = {};
  for (const d of dispatches.value) {
    const machineId = d.batch?.machine?.id ?? d.batch?.machine_id;
    if (!machineId || !d.created_at) continue;
    if (!map[machineId] || new Date(d.created_at) < new Date(map[machineId])) {
      map[machineId] = d.created_at;
    }
  }
  return map;
});

const dispatchAgeHours = (machineId: number): number | null => {
  const t = oldestPendingByMachine.value[machineId];
  if (!t) return null;
  return (Date.now() - new Date(t).getTime()) / 3600000;
};

const dispatchAgeClass = (machineId: number): string => {
  const hours = dispatchAgeHours(machineId);
  if (hours === null) return 'age-none';
  if (hours < 24) return 'age-fresh';
  if (hours < 48) return 'age-warn';
  return 'age-stale';
};

const dispatchAgeTitle = (machineId: number): string => {
  const hours = dispatchAgeHours(machineId);
  if (hours === null) return 'Không có lệnh nào đang chờ gửi máy';
  return `Lệnh chờ cũ nhất: ${hours.toFixed(1)}h trước`;
};

// Đơn/lô đang chạy trên từng thùng cụ thể — đúng ý mainform hiển thị color/code
// đang nằm ở mỗi ô lưới (LoadAllVD_Input), chỉ khác nguồn là machine_dispatches
// (Postgres) thay vì tbl_input_all (Access). Nếu 1 thùng có nhiều lệnh đang chờ
// cùng lúc, chỉ lấy lệnh MỚI NHẤT làm đại diện hiển thị.
const dispatchesByTank = computed(() => {
  const map: Record<number, any[]> = {};
  for (const d of dispatches.value) {
    const tankId = d.batch?.tank?.id ?? d.batch?.tank_id;
    if (!tankId) continue;
    if (!map[tankId]) map[tankId] = [];
    map[tankId].push(d);
  }
  for (const key of Object.keys(map)) {
    map[Number(key)].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  }
  return map;
});

const activeDispatchForTank = (tankId: number) => {
  const list = dispatchesByTank.value[tankId];
  return list && list.length ? list[0] : null;
};

// Ma trận sẵn cho template: cellData[machineId][rowIndex] = { tank, dispatch }.
const cellData = computed(() => {
  const map: Record<number, Array<{ tank: any; dispatch: any | null }>> = {};
  for (const m of filteredMachines.value) {
    const tanksForMachine = tanksByMachine.value[m.id] || [];
    map[m.id] = tanksForMachine.map(t => ({
      tank: t,
      dispatch: activeDispatchForTank(t.id)
    }));
  }
  return map;
});

const stateLabel = (state: string): string => {
  const labels: Record<string, string> = {
    INPUT: 'Mới nhập',
    WAITING: 'Đang chờ',
    TO_SEND: 'Chờ gửi máy',
    PROCESSING: 'Đang chạy',
    ERROR: 'Lỗi',
    CONFIRMED: 'Đã duyệt',
    SENT: 'Đã gửi máy'
  };
  return labels[state] || state;
};

const orderTooltip = (dispatch: any): string => {
  const hours = (Date.now() - new Date(dispatch.created_at).getTime()) / 3600000;
  return `${dispatch.batch?.color} · ${dispatch.batch?.product_code} — ${stateLabel(dispatch.queue_state)} (${hours.toFixed(1)}h trước)`;
};

const openAddModal = () => {
  addForm.value = { code: '', name: '' };
  addError.value = '';
  showAddModal.value = true;
};

const closeAddModal = () => {
  showAddModal.value = false;
};

const submitAddMachine = async () => {
  addError.value = '';
  if (!addForm.value.code.trim() || !addForm.value.name.trim()) {
    addError.value = 'Nhập đủ Mã máy và Tên máy.';
    return;
  }

  saving.value = true;
  try {
    await axios.post('/api/machines', addForm.value, { headers: authHeaders() });
    await fetchMachines();
    closeAddModal();
  } catch (error: any) {
    addError.value = error.response?.data?.message || 'Không thêm được máy mới (mã có thể đã tồn tại).';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchAll();
  // Tự làm mới mỗi 10 giây — nhanh hơn hẳn chu kỳ 3 phút gốc của StartAutoVD/RunAutoVD
  // bên VBA, theo yêu cầu xem gần-real-time hơn (2026-07-29). Lưu ý: KHÔNG liên quan
  // tới record.accdb — vẫn chỉ gọi lại API Postgres (/api/machines, /api/tanks,
  // /api/machine-dispatches), Access không phải nguồn dữ liệu của trang này.
  autoRefreshTimer = setInterval(fetchAll, 10000);
});

onUnmounted(() => {
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
});
</script>

<style scoped>
.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}
.search-box {
  width: 220px;
}
.refresh-btn,
.add-btn {
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}
.refresh-btn:hover,
.add-btn:hover {
  background: var(--primary);
  color: #fff;
}

/* Banner cố định màu, gợi lại đúng tiêu đề mainform VBA (nền xanh-xám nhạt kiểu
   MS Forms cổ điển) — không đổi theo theme sáng/tối của app. */
.vba-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #dfe7f2;
  border: 1px solid #a9bcd6;
  border-radius: 4px;
  padding: 10px 16px;
}
.vba-open-form-btn {
  margin-left: auto;
  background: #3a5a8c;
  color: #fff;
  border: 1px solid #2a436b;
  border-radius: 4px;
  padding: 6px 14px;
  font-family: 'Segoe UI', Tahoma, sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.2s ease;
}
.vba-open-form-btn:hover {
  background: #2a436b;
}
.vba-logo {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
  color: #1f3864;
  letter-spacing: 0.05em;
}
.vba-title {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  font-weight: 700;
  font-size: 1.05rem;
  color: #14284d;
  letter-spacing: 0.03em;
}

.vd-grid-wrapper {
  overflow-x: auto;
  border: 1px solid var(--border-card);
  border-radius: 8px;
}

.vd-grid-table {
  border-collapse: collapse;
  width: 100%;
  font-family: 'Segoe UI', Tahoma, sans-serif;
}

.vd-grid-table th {
  background: #dfe7f2;
  color: #14284d;
  border: 1px solid #a9bcd6;
  padding: 8px 14px;
  font-weight: 700;
  white-space: nowrap;
  position: sticky;
  top: 0;
}

/* Tô màu tuổi dữ liệu — đúng ngưỡng và mã màu RGB gốc trong mainform VBA
   (Mod_load_input.LoadAllVD_Input): trắng <24h, xanh nhạt 24-48h, tím >48h. */
.vd-grid-table th.age-fresh {
  background: rgb(255, 255, 255);
  color: #14284d;
}
.vd-grid-table th.age-warn {
  background: rgb(180, 205, 230);
  color: #14284d;
}
.vd-grid-table th.age-stale {
  background: rgb(180, 160, 200);
  color: #2a1240;
}
.vd-grid-table th.age-none {
  background: #dfe7f2;
  opacity: 0.6;
}

.age-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  font-size: 0.8rem;
  color: var(--text-subtitle);
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}
.legend-swatch {
  width: 14px;
  height: 14px;
  border-radius: 3px;
  border: 1px solid #a9bcd6;
  display: inline-block;
}
.swatch-fresh { background: rgb(255, 255, 255); }
.swatch-warn { background: rgb(180, 205, 230); }
.swatch-stale { background: rgb(180, 160, 200); }
.swatch-none { background: #dfe7f2; opacity: 0.6; }

.source-footnote {
  font-size: 0.78rem;
  color: var(--text-subtitle);
  line-height: 1.6;
}
.source-footnote code {
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  border-radius: 4px;
  padding: 1px 6px;
}

.vd-grid-table td {
  border: 1px solid var(--border-divider);
  padding: 8px 10px;
  text-align: center;
  color: var(--text-body);
  background: var(--bg-card);
  vertical-align: top;
}

.vd-grid-table td.empty-cell {
  color: var(--text-subtitle);
  white-space: nowrap;
}

.tank-cell {
  min-width: 130px;
}

.tank-code {
  font-weight: 700;
  color: var(--text-title);
  margin-bottom: 4px;
}

.tank-idle {
  font-size: 0.75rem;
  color: var(--text-subtitle);
  font-style: italic;
}

.tank-order {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 4px 6px;
  border-radius: 6px;
  font-size: 0.75rem;
  line-height: 1.3;
}

.order-line {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.order-badge {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  align-self: flex-start;
  padding: 1px 6px;
  border-radius: 10px;
}

/* Màu theo queue_state — "Đang chạy" (PROCESSING) là trọng tâm câu hỏi: thùng nào
   đang thật sự có mẻ chạy, khác với chỉ "đang chờ" trong hàng đợi. */
.tank-order.state-INPUT { background: rgba(148, 163, 184, 0.15); }
.tank-order.state-INPUT .order-badge { background: rgba(148, 163, 184, 0.3); color: #475569; }

.tank-order.state-WAITING { background: rgba(234, 179, 8, 0.12); }
.tank-order.state-WAITING .order-badge { background: rgba(234, 179, 8, 0.3); color: #92660a; }

.tank-order.state-TO_SEND { background: rgba(59, 130, 246, 0.12); }
.tank-order.state-TO_SEND .order-badge { background: rgba(59, 130, 246, 0.3); color: #1d4ed8; }

.tank-order.state-PROCESSING { background: rgba(16, 185, 129, 0.15); }
.tank-order.state-PROCESSING .order-badge { background: rgba(16, 185, 129, 0.35); color: #047857; }

.tank-order.state-ERROR { background: rgba(239, 68, 68, 0.15); }
.tank-order.state-ERROR .order-badge { background: rgba(239, 68, 68, 0.35); color: #b91c1c; }

.tank-order.state-CONFIRMED { background: rgba(99, 102, 241, 0.12); }
.tank-order.state-CONFIRMED .order-badge { background: rgba(99, 102, 241, 0.3); color: #4338ca; }

.tank-order.state-SENT { background: rgba(249, 115, 22, 0.12); }
.tank-order.state-SENT .order-badge { background: rgba(249, 115, 22, 0.3); color: #c2410c; }

.empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-top: 1px dashed var(--border-card);
  color: var(--text-subtitle);
}

/* Modal */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}
.modal-card {
  background: hsl(224, 25%, 16%);
  border: 1px solid var(--border-card);
  border-radius: 12px;
  width: 420px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.2rem;
  border-bottom: 1px solid var(--border-divider);
}
.close-modal-btn {
  background: transparent;
  border: none;
  color: var(--text-subtitle);
  font-size: 1.2rem;
  cursor: pointer;
}
.close-modal-btn:hover {
  color: var(--text-title);
}
.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-divider);
  background: hsla(224, 25%, 12%, 0.5);
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
}
.cancel-btn {
  background: transparent;
  border: 1px solid var(--border-card);
  color: var(--text-subtitle);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}
.cancel-btn:hover {
  color: var(--text-title);
  background: var(--bg-tag);
}
.submit-btn {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
