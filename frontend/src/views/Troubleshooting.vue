<template>
  <div class="dashboard-container">
    <!-- Main Content -->
    <main class="main-content">
      <div class="tabs-control">
        <button @click="activeTab = 'diagnose'" :class="['tab-btn', activeTab === 'diagnose' ? 'tab-active' : '']">🔬 Chẩn Đoán Lỗi Mới</button>
        <button @click="activeTab = 'history'" :class="['tab-btn', activeTab === 'history' ? 'tab-active' : '']">📜 Lịch Sử Chẩn Đoán</button>
      </div>

      <!-- Diagnose Tab -->
      <div v-if="activeTab === 'diagnose'" class="trouble-grid">
        <!-- Input Form -->
        <section class="section diagnose-form-sec">
          <h3>🔬 Nhập Hiện Tượng & Thông Số</h3>
          <p class="section-desc">Khai báo các hiện tượng bất thường quan sát được và giá trị đo lường thực tế tại hiện trường để phân tích nguyên nhân.</p>

          <form @submit.prevent="runDiagnosis" class="diagnose-form">
            <!-- Batch Select -->
            <div class="form-group">
              <label for="batch-select">📦 Lô sản xuất (Tùy chọn):</label>
              <select id="batch-select" v-model="form.batch_id" class="form-select">
                <option value="">-- Không chọn / Không có lô --</option>
                <option v-for="b in batches" :key="b.id" :value="b.id">
                  {{ b.legacy_batch_id }} - {{ b.color }} ({{ b.product_code }})
                </option>
              </select>
            </div>

            <!-- Process Stage -->
            <div class="form-group">
              <label for="stage-select">⚙️ Công đoạn hiện tại <span class="required">*</span>:</label>
              <select id="stage-select" v-model="form.stage_id" required class="form-select">
                <option value="">-- Chọn công đoạn --</option>
                <option v-for="p in processes" :key="p.id" :value="p.id">
                  {{ p.id }} - {{ p.process_name }}
                </option>
              </select>
            </div>

            <!-- Problem / Symptoms Checkbox Grid -->
            <div class="form-group">
              <label class="group-label">⚠️ Hiện tượng lỗi quan sát được (Chọn ít nhất 1) <span class="required">*</span>:</label>
              <div class="problems-grid">
                <div v-for="prob in problems" :key="prob.id" class="checkbox-card" :class="{ 'card-checked': form.problems.includes(prob.id) }">
                  <input type="checkbox" :id="'prob-' + prob.id" :value="prob.id" v-model="form.problems" />
                  <label :for="'prob-' + prob.id">
                    <strong>{{ prob.id }}</strong> - {{ prob.problem_name }}
                  </label>
                </div>
              </div>
            </div>

            <!-- Numeric Parameters Input -->
            <div class="form-group">
              <label class="group-label">🌡️ Thông số đo lường vật lý thực tế:</label>
              <div class="numeric-inputs-grid">
                <div v-for="param in numericParams" :key="param.name" class="param-card">
                  <div class="param-header">
                    <strong>{{ param.name }}</strong>
                    <span class="param-unit">({{ param.unit }})</span>
                  </div>
                  <div class="param-body">
                    <div class="input-subgroup">
                      <label>Setpoint:</label>
                      <input type="number" step="any" v-model.number="form.parameters[param.name].set" placeholder="Ví dụ: 105" class="form-control" />
                    </div>
                    <div class="input-subgroup">
                      <label>Thực tế:</label>
                      <input type="number" step="any" v-model.number="form.parameters[param.name].actual" placeholder="Ví dụ: 95" class="form-control" />
                    </div>
                  </div>
                  <div class="param-spec-info" v-if="param.lsl || param.usl">
                    Lệch spec nếu: &lt; Setpoint - {{ param.lsl }} hoặc &gt; Setpoint + {{ param.usl }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Categorical Parameters Selectors -->
            <div class="form-group">
              <label class="group-label">🌫️ Các trạng thái môi trường & thiết bị khác:</label>
              <div class="categorical-inputs-grid">
                <div v-for="param in categoricalParams" :key="param.name" class="categorical-card">
                  <label :for="'cat-' + param.name">{{ param.label }}:</label>
                  <select :id="'cat-' + param.name" v-model="form.parameters[param.name]" class="form-select">
                    <option value="NORMAL">NORMAL (Bình thường)</option>
                    <option value="LOW">LOW (Thấp / Kém)</option>
                    <option value="HIGH">HIGH (Cao / Ẩm)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
              <button type="submit" class="submit-btn" :disabled="loading">
                {{ loading ? '⏳ Đang phân tích...' : '🔬 Bắt Đầu Suy Luận Chẩn Đoán' }}
              </button>
            </div>
          </form>
        </section>

        <!-- Diagnosis Result Panel -->
        <section class="section result-panel-sec">
          <div class="section-header-row">
            <h3>📊 Kết Quả Chẩn Đoán Nguyên Nhân Gợi Ý</h3>
            <span class="badge" v-if="results.length > 0">Tìm thấy {{ results.length }} nguyên nhân tiềm ẩn</span>
          </div>

          <!-- Empty State -->
          <div v-if="results.length === 0" class="empty-results-state">
            <span class="empty-icon">🔍</span>
            <p>Chưa có kết quả chẩn đoán.</p>
            <p class="empty-sub">Hãy chọn hiện tượng, điền các thông số vật lý và bấm "Bắt Đầu Suy Luận Chẩn Đoán" để nhận gợi ý xếp hạng nguyên nhân.</p>
          </div>

          <!-- Recommendations list -->
          <div v-else class="results-list">
            <div v-for="res in results" :key="res.cause_id" class="cause-card" :class="'severity-' + (res.severity || 'MEDIUM').toLowerCase()">
              <div class="cause-card-header">
                <div class="rank-badge">#{{ res.rank }}</div>
                <div class="cause-meta">
                  <h4>{{ res.cause_id }} - {{ res.cause_name }}</h4>
                  <div class="tags-row">
                    <span class="tag-badge cat-badge">{{ res.category }}</span>
                    <span class="tag-badge sev-badge">Độ nghiêm trọng: {{ res.severity || 'Trung bình' }}</span>
                    <span class="tag-badge prio-badge">Sửa chữa ưu tiên: {{ res.repair_priority || 'Bình thường' }}</span>
                  </div>
                </div>
                <div class="score-display">
                  <span class="score-number">{{ res.score }}</span>
                  <span class="score-label">điểm</span>
                </div>
              </div>

              <!-- Details Breakdown -->
              <div class="cause-breakdown-details">
                <div class="breakdown-summary">
                  <span>Trọng số hiện tượng: <strong>{{ res.breakdown.problem_score }}</strong></span>
                  <span>Trọng số công đoạn: <strong>{{ res.breakdown.process_score }}</strong></span>
                  <span>Trọng số thông số: <strong>{{ res.breakdown.parameter_score }}</strong></span>
                </div>
                <ul class="breakdown-details-list">
                  <li v-for="(detail, index) in res.breakdown.details" :key="index">
                    🔧 {{ detail }}
                  </li>
                </ul>
              </div>

              <!-- Action to resolve -->
              <div class="cause-card-actions">
                <button @click="openResolveModal(res)" class="action-btn resolve-trigger-btn">
                  ✅ Xác nhận đây là nguyên nhân thực tế
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- History Tab -->
      <div v-if="activeTab === 'history'" class="history-container">
        <section class="section">
          <h3>📜 Nhật Ký Các Ca Sự Cố Đã Ghi Nhận</h3>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Mã Ca</th>
                  <th>Thời Gian</th>
                  <th>Mẻ Sản Xuất</th>
                  <th>Công Đoạn</th>
                  <th>Người Báo Cáo</th>
                  <th>Trạng Thái</th>
                  <th>Nguyên Nhân Thực Tế</th>
                  <th>Biện Pháp Xử Lý</th>
                  <th>Hiệu Quả</th>
                  <th>Thao Tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in cases" :key="c.id" :class="'status-' + c.status.toLowerCase()">
                  <td><code>{{ c.id.substring(0, 8) }}...</code></td>
                  <td>{{ formatDate(c.created_at) }}</td>
                  <td>{{ c.batch ? c.batch.legacy_batch_id : 'N/A' }}</td>
                  <td>{{ c.stage ? c.stage.process_name : 'N/A' }}</td>
                  <td>{{ c.reporter ? c.reporter.display_name : 'Hệ thống' }}</td>
                  <td>
                    <span :class="['status-badge', c.status === 'CLOSED' ? 'status-closed' : 'status-open']">
                      {{ c.status }}
                    </span>
                  </td>
                  <td>
                    <strong v-if="c.actual_cause">{{ c.actual_cause.id }} - {{ c.actual_cause.cause_name }}</strong>
                    <span v-else class="text-muted">Chưa xác định</span>
                  </td>
                  <td>
                    <span class="truncate-text" :title="c.resolution_notes">{{ c.resolution_notes || 'N/A' }}</span>
                  </td>
                  <td>
                    <div v-if="c.effectiveness_rating" class="stars-display">
                      <span v-for="star in 5" :key="star" class="star" :class="{ 'star-filled': star <= c.effectiveness_rating }">★</span>
                    </div>
                    <span v-else>N/A</span>
                  </td>
                  <td>
                    <button @click="viewCaseDetails(c.id)" class="action-btn details-btn">👁️ Xem Chi Tiết</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>

    <!-- Case Details Modal -->
    <div v-if="detailModal.show" class="modal-overlay" @click.self="detailModal.show = false">
      <div class="modal-card detail-modal-card">
        <div class="modal-header">
          <h3>🔍 Chi Tiết Ca Sự Cố #{{ detailModal.case.id.substring(0, 8) }}</h3>
          <button @click="detailModal.show = false" class="close-modal-btn">&times;</button>
        </div>
        <div class="modal-body" v-if="detailModal.case">
          <div class="meta-info-grid">
            <div><strong>Mẻ Nhuộm:</strong> {{ detailModal.case.batch ? detailModal.case.batch.legacy_batch_id : 'N/A' }}</div>
            <div><strong>Công Đoạn:</strong> {{ detailModal.case.stage ? detailModal.case.stage.process_name : 'N/A' }}</div>
            <div><strong>Thời Gian:</strong> {{ formatDate(detailModal.case.created_at) }}</div>
            <div><strong>Kỹ Thuật Viên:</strong> {{ detailModal.case.reporter ? detailModal.case.reporter.display_name : 'N/A' }}</div>
          </div>

          <h5 class="modal-section-title">⚠️ Hiện tượng lỗi được báo cáo:</h5>
          <div class="symptoms-tags">
            <span v-for="prob in detailModal.case.problems" :key="prob.id" class="symptom-tag">
              {{ prob.id }} - {{ prob.problem_name }}
            </span>
          </div>

          <h5 class="modal-section-title">📊 Minh chứng & Giá trị đo lường:</h5>
          <div class="evidence-grid">
            <div v-for="ev in detailModal.case.evidences" :key="ev.id" class="evidence-card" :class="'ev-' + (ev.status || 'NORMAL').toLowerCase()">
              <div class="ev-name">{{ ev.parameter ? ev.parameter.parameter_name : 'N/A' }}</div>
              <div class="ev-values" v-if="ev.actual_value !== null">
                Thực tế: <strong>{{ ev.actual_value }}</strong> / Setpoint: {{ ev.setpoint_value }}
              </div>
              <div class="ev-status">Trạng thái: <strong>{{ ev.status }}</strong></div>
            </div>
          </div>

          <div v-if="detailModal.case.status === 'CLOSED'" class="resolution-panel">
            <h5 class="modal-section-title">✅ Kết quả xử lý sự cố thực tế:</h5>
            <div class="closed-info-card">
              <div><strong>Nguyên Nhân Xác Định:</strong> {{ detailModal.case.actual_cause.id }} - {{ detailModal.case.actual_cause.cause_name }}</div>
              <div class="mt-2"><strong>Biện Pháp Khắc Phục:</strong></div>
              <p class="notes-p">{{ detailModal.case.resolution_notes }}</p>
              <div class="mt-2"><strong>Đánh giá độ hiệu quả:</strong></div>
              <div class="stars-display large-stars">
                <span v-for="star in 5" :key="star" class="star" :class="{ 'star-filled': star <= detailModal.case.effectiveness_rating }">★</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resolve Case Modal (Feedback loop form) -->
    <div v-if="resolveModal.show" class="modal-overlay" @click.self="resolveModal.show = false">
      <div class="modal-card resolve-modal-card">
        <div class="modal-header">
          <h3>✅ Xác Nhận Kết Quả Xử Lý Sự Cố</h3>
          <button @click="resolveModal.show = false" class="close-modal-btn">&times;</button>
        </div>
        <form @submit.prevent="submitResolution" class="resolve-form">
          <div class="modal-body">
            <p class="modal-desc">Đóng case chẩn đoán bằng cách cập nhật nguyên nhân thực tế tìm thấy và biện pháp xử lý để tối ưu hóa tri thức hệ thống.</p>
            
            <div class="form-group">
              <label>Nguyên nhân tìm thấy thực tế:</label>
              <input type="text" readonly :value="resolveModal.cause.cause_id + ' - ' + resolveModal.cause.cause_name" class="form-control readonly-control" />
            </div>

            <div class="form-group">
              <label for="res-notes">🛠️ Biện pháp khắc phục / Nhật ký sửa chữa:</label>
              <textarea id="res-notes" v-model="resolveModal.notes" required placeholder="Ghi chú chi tiết thiết bị hỏng, cách sửa chữa để lưu trữ tri thức..." rows="4" class="form-control"></textarea>
            </div>

            <div class="form-group">
              <label>⭐ Đánh giá độ hiệu quả của chẩn đoán:</label>
              <div class="rating-stars-interactive">
                <span v-for="star in 5" :key="star" @click="resolveModal.rating = star" class="star-interactive" :class="{ 'star-selected': star <= resolveModal.rating }">★</span>
              </div>
              <span class="rating-hint">{{ getRatingHint(resolveModal.rating) }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="resolveModal.show = false" class="modal-btn-cancel">Hủy</button>
            <button type="submit" class="modal-btn-submit">💾 Lưu & Đóng Ca Chẩn Đoán</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';

// `ref([])` để trần bị suy ra `never[]`, mọi chỗ đọc phần tử sau đó đều báo lỗi. Dùng `any[]`
// cho đồng nhất với các màn khác trong dự án (dữ liệu tới thẳng từ API, chưa có DTO chung).
const activeTab = ref('diagnose');
const loading = ref(false);
const problems = ref<any[]>([]);
const processes = ref<any[]>([]);
const batches = ref<any[]>([]);
const cases = ref<any[]>([]);
const results = ref<any[]>([]);

const numericParams = [
  { name: 'Temperature', unit: '℃', lsl: 100.0, usl: 120.0 },
  { name: 'Steam', unit: 'kg/cm²', lsl: 4.0, usl: 5.0 },
  { name: 'Air Pressure', unit: 'bar', lsl: 4.0, usl: 5.0 },
  { name: 'Speed', unit: 'm/min', lsl: 20.0, usl: 30.0 }
];

const categoricalParams = [
  { name: 'Humidity', label: '🌫️ Độ ẩm phòng dệt/ép nóng' },
  { name: 'Steam', label: '💨 Chất lượng hơi cấp (Steam Dancer)' },
  { name: 'Washing', label: '💧 Nước xả giặt sạch' },
  { name: 'Dryer', label: '🔥 Máy sấy khô hoạt động' }
];

// Diagnose Form state
// `parameters` được truy cập bằng tên tham số động (form.parameters[p.name]) nên phải khai là
// bản đồ chuỗi -> giá trị, nếu không TS chỉ cho đọc đúng 7 khoá viết cứng bên dưới.
const form = reactive({
  batch_id: '',
  stage_id: '',
  problems: [] as any[],
  parameters: {
    Temperature: { actual: null, set: null },
    Steam: { actual: null, set: null },
    'Air Pressure': { actual: null, set: null },
    Speed: { actual: null, set: null },
    Humidity: 'NORMAL',
    Washing: 'NORMAL',
    Dryer: 'NORMAL'
  } as Record<string, any>
});

// Resolve Modal state
const resolveModal = reactive({
  show: false,
  caseId: null as string | null,
  cause: {} as Record<string, any>,
  notes: '',
  rating: 5
});

// Detail Modal state
const detailModal = reactive({
  show: false,
  case: null as any
});

onMounted(async () => {
  await fetchProblems();
  await fetchProcesses();
  await fetchBatches();
  await fetchCases();
});

const fetchProblems = async () => {
  try {
    const res = await axios.get('/api/troubleshooting/problems');
    problems.value = res.data.data;
  } catch (err) {
    console.error('Lỗi khi tải hiện tượng lỗi:', err);
  }
};

const fetchProcesses = async () => {
  try {
    const res = await axios.get('/api/troubleshooting/processes');
    processes.value = res.data.data;
  } catch (err) {
    console.error('Lỗi khi tải công đoạn:', err);
  }
};

const fetchBatches = async () => {
  try {
    const res = await axios.get('/api/production-batches');
    batches.value = res.data.data;
  } catch (err) {
    console.error('Lỗi khi tải lô sản xuất:', err);
  }
};

const fetchCases = async () => {
  try {
    const res = await axios.get('/api/troubleshooting/cases');
    cases.value = res.data.data;
  } catch (err) {
    console.error('Lỗi khi tải lịch sử sự cố:', err);
  }
};

const runDiagnosis = async () => {
  if (form.problems.length === 0) {
    alert('Vui lòng chọn ít nhất một hiện tượng lỗi để chẩn đoán!');
    return;
  }
  
  loading.value = true;
  try {
    // Filter out parameters that do not have values
    const payloadParams: Record<string, any> = {};
    
    // Numeric
    numericParams.forEach(p => {
      const act = form.parameters[p.name].actual;
      const set = form.parameters[p.name].set;
      if (act !== null && set !== null && act !== '' && set !== '') {
        payloadParams[p.name] = { actual: Number(act), set: Number(set) };
      }
    });

    // Categorical
    categoricalParams.forEach(p => {
      payloadParams[p.name] = form.parameters[p.name];
    });

    const payload = {
      batch_id: form.batch_id || null,
      stage_id: form.stage_id,
      problems: form.problems,
      parameters: payloadParams
    };

    const res = await axios.post('/api/troubleshooting/diagnose', payload);
    resolveModal.caseId = res.data.data.case_id;
    results.value = res.data.data.recommendations;
    
    // Refresh history
    await fetchCases();
  } catch (err) {
    console.error('Lỗi chẩn đoán:', err);
    alert('Không thể thực hiện chẩn đoán. Vui lòng kiểm tra lại dữ liệu.');
  } finally {
    loading.value = false;
  }
};

const openResolveModal = (res: any) => {
  resolveModal.cause = res;
  resolveModal.notes = '';
  resolveModal.rating = 5;
  resolveModal.show = true;
};

const submitResolution = async () => {
  if (!resolveModal.caseId) {
    alert('Mã sự cố không hợp lệ.');
    return;
  }

  try {
    await axios.post(`/api/troubleshooting/cases/${resolveModal.caseId}/resolve`, {
      actual_cause_id: resolveModal.cause.cause_id,
      resolution_notes: resolveModal.notes,
      effectiveness_rating: resolveModal.rating
    });

    resolveModal.show = false;
    alert('Đã đóng ca chẩn đoán sự cố và cập nhật phản hồi hiệu quả thành công!');
    
    // Reset results & fetch history
    results.value = [];
    activeTab.value = 'history';
    await fetchCases();
  } catch (err) {
    console.error('Lỗi đóng case:', err);
    alert('Không thể hoàn tất đóng case sự cố.');
  }
};

const viewCaseDetails = async (caseId: string) => {
  try {
    const res = await axios.get(`/api/troubleshooting/cases/${caseId}`);
    detailModal.case = res.data.data;
    detailModal.show = true;
  } catch (err) {
    console.error('Lỗi tải chi tiết ca sự cố:', err);
  }
};

const getRatingHint = (rating: number) => {
  const hints: Record<number, string> = {
    1: 'Rất kém (Không đúng nguyên nhân)',
    2: 'Kém (Gợi ý sai lệch lớn)',
    3: 'Bình thường (Đúng nguyên nhân nhưng hạng thấp)',
    4: 'Tốt (Đúng nguyên nhân trong Top 3)',
    5: 'Xuất sắc (Chẩn đoán chính xác nguyên nhân ở hạng #1)'
  };
  return hints[rating] || '';
};

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric', hour12: false });
};

</script>

<style scoped>
/* Tabs controls */
.tabs-control {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 12px;
}
.tab-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #ccc;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}
.tab-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
.tab-active {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  border-color: #10b981;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Troubleshoot layouts */
.trouble-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

@media (max-width: 1024px) {
  .trouble-grid {
    grid-template-columns: 1fr;
  }
}

/* Checkboxes cards */
.problems-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-top: 8px;
}
.checkbox-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.checkbox-card input {
  width: 18px;
  height: 18px;
  cursor: pointer;
}
.checkbox-card label {
  cursor: pointer;
  font-size: 0.9rem;
  line-height: 1.2;
}
.checkbox-card:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.15);
}
.card-checked {
  background: rgba(16, 185, 129, 0.08);
  border-color: #10b981;
}

/* Numeric parameters layout */
.numeric-inputs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 8px;
}
.param-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  padding: 12px;
}
.param-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 0.95rem;
}
.param-body {
  display: flex;
  gap: 8px;
}
.input-subgroup {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.input-subgroup label {
  font-size: 0.75rem;
  color: #aaa;
}
.param-spec-info {
  margin-top: 8px;
  font-size: 0.75rem;
  color: #888;
}

/* Categorical inputs */
.categorical-inputs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 8px;
}
.categorical-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.categorical-card label {
  font-size: 0.85rem;
  color: #ccc;
}

/* Results panel */
.empty-results-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  background: rgba(255, 255, 255, 0.02);
  border: 2px dashed rgba(255, 255, 255, 0.05);
  border-radius: 12px;
}
.empty-icon {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.6;
}
.empty-sub {
  color: #888;
  font-size: 0.85rem;
  max-width: 320px;
  margin-top: 8px;
}

/* Cause recommendation cards */
.results-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 75vh;
  overflow-y: auto;
  padding-right: 8px;
}
.cause-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: all 0.3s ease;
}
.cause-card:hover {
  transform: translateY(-2px);
  border-color: rgba(255, 255, 255, 0.15);
}

.cause-card-header {
  display: flex;
  align-items: center;
  gap: 16px;
}
.rank-badge {
  background: #10b981;
  color: #fff;
  font-size: 1.1rem;
  font-weight: 700;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}
.cause-meta {
  flex: 1;
}
.cause-meta h4 {
  margin: 0 0 6px 0;
  font-size: 1.1rem;
  color: #fff;
}
.tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.tag-badge {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 500;
}
.cat-badge {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.3);
}
.sev-badge {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}
.sev-high .sev-badge {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
.prio-badge {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
.score-display {
  text-align: right;
  display: flex;
  flex-direction: column;
}
.score-number {
  font-size: 1.6rem;
  font-weight: 800;
  color: #10b981;
  line-height: 1;
}
.score-label {
  font-size: 0.75rem;
  color: #888;
}

/* Breakdown styles */
.cause-breakdown-details {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 8px;
  padding: 12px;
  font-size: 0.85rem;
}
.breakdown-summary {
  display: flex;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  padding-bottom: 8px;
  margin-bottom: 8px;
  color: #aaa;
}
.breakdown-summary strong {
  color: #fff;
}
.breakdown-details-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.breakdown-details-list li {
  color: #d1d5db;
}

/* Resolve trigger button */
.cause-card-actions {
  display: flex;
  justify-content: flex-end;
}
.resolve-trigger-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #10b981;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s ease;
}
.resolve-trigger-btn:hover {
  background: #10b981;
  color: #fff;
  border-color: #10b981;
}

/* Stars Rating Display */
.stars-display {
  display: flex;
  gap: 2px;
  color: #4b5563;
}
.star-filled {
  color: #fbbf24;
}
.large-stars {
  font-size: 1.5rem;
}

/* Interative stars rating */
.rating-stars-interactive {
  display: flex;
  gap: 8px;
  font-size: 2rem;
  margin-bottom: 6px;
}
.star-interactive {
  cursor: pointer;
  color: #4b5563;
  transition: all 0.2s ease;
}
.star-interactive:hover,
.star-selected {
  color: #fbbf24;
  transform: scale(1.1);
}
.rating-hint {
  font-size: 0.85rem;
  color: #aaa;
  font-style: italic;
}

/* Modal styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-card {
  /* Cũng là nền tối cố định ở cả hai theme — đổi sang navy cho khớp dark theme.
     (Việc modal này tối cả khi đang ở theme sáng là chuyện có sẵn, chưa đụng tới.) */
  background: hsl(217, 40%, 13%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
  width: 90%;
  max-width: 550px;
  overflow: hidden;
  animation: modalEnter 0.3s ease;
}

@keyframes modalEnter {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  padding: 16px 20px;
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.2rem;
  color: #fff;
}
.close-modal-btn {
  background: none;
  border: none;
  color: #aaa;
  font-size: 1.8rem;
  cursor: pointer;
}
.close-modal-btn:hover {
  color: #fff;
}

.modal-body {
  padding: 20px;
  max-height: 70vh;
  overflow-y: auto;
}
.modal-desc {
  font-size: 0.85rem;
  color: #aaa;
  margin-bottom: 16px;
}
.modal-section-title {
  margin: 16px 0 8px 0;
  color: #60a5fa;
  font-size: 0.95rem;
  font-weight: 600;
}

/* Symptoms Tags */
.symptoms-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.symptom-tag {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.85rem;
}

/* Evidence grid */
.evidence-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.evidence-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  padding: 10px;
  font-size: 0.85rem;
}
.ev-values {
  margin-top: 4px;
}
.ev-low {
  border-left: 3px solid #f87171;
}
.ev-high {
  border-left: 3px solid #fbbf24;
}

/* Closed info card */
.closed-info-card {
  background: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 8px;
  padding: 12px;
  font-size: 0.9rem;
}
.notes-p {
  background: rgba(0, 0, 0, 0.2);
  padding: 8px;
  border-radius: 6px;
  margin-top: 4px;
  font-family: inherit;
  font-size: 0.85rem;
}

/* Modal footer buttons */
.modal-footer {
  padding: 16px 20px;
  background: rgba(255, 255, 255, 0.02);
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
.modal-btn-cancel {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #ccc;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.modal-btn-submit {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border: none;
  color: #fff;
  padding: 8px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}

.readonly-control {
  background: rgba(255, 255, 255, 0.02) !important;
  color: #aaa !important;
  cursor: not-allowed;
}

/* Layout utilities */
.truncate-text {
  display: inline-block;
  max-width: 180px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.status-badge {
  font-size: 0.8rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}
.status-closed {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
}
.status-open {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}
.mt-2 {
  margin-top: 8px;
}
</style>
