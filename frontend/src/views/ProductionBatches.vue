<template>
  <div class="batches-view-container">
    <!-- Page Header -->
    <div class="page-header-row">
      <div class="header-titles">
        <h2>{{ $t('productionBatches.pageTitle') }}</h2>
        <p class="text-muted">{{ $t('productionBatches.pageDesc') }}</p>
      </div>
      <div class="header-actions">
        <router-link to="/production-batches/list" class="btn btn-secondary">
          <SvgIcon name="batch" size="18" />
          {{ $t('productionBatches.navList') }}
        </router-link>
        <router-link to="/production-batches/grid" class="btn btn-secondary">
          <SvgIcon name="batch" size="18" />
          {{ $t('productionBatches.navGrid') }}
        </router-link>
      </div>
    </div>

    <!-- Trạm quét đơn thật (WS-ORDER-01) — port đúng MainForm VBA "2.C3 grid load row
         lock id FB -192(QR).xlsm": quét QR "ALL DATA" từ MES vào 1 ô duy nhất, hệ thống
         tự tách màu/mã hàng/máy/mức nước, người vận hành chọn Thùng rồi PHÊ DUYỆT + SAVE.
         Xác nhận bằng 2 mẫu QR thật (phiếu MES BEST PACIFIC + ảnh chụp MainForm đang chạy). -->
    <section class="section card-sec scan-order-panel mb-4">
      <div class="scan-panel-header">
        <h3>{{ $t('productionBatches.panelTitle') }}</h3>
        <span class="text-muted">{{ $t('productionBatches.panelDesc') }}</span>
      </div>

      <div class="scan-input-row">
        <label>{{ $t('productionBatches.scanLabel') }}</label>
        <input
          ref="scanInputRef"
          @keyup.enter="handleScan"
          type="text"
          autocomplete="off"
          :disabled="scanning"
          :placeholder="scanning ? $t('productionBatches.scanPlaceholderBusy') : $t('productionBatches.scanPlaceholderIdle')"
          class="form-control scan-input-large"
        />
      </div>

      <p v-if="debugRawScan" class="text-muted mono-text scan-debug-line">
        {{ $t('productionBatches.debugRawScanLine', { value: debugRawScan }) }}
      </p>

      <div class="scan-fields-grid">
        <div class="form-group">
          <label>{{ $t('productionBatches.labelColor') }}</label>
          <input v-model="scanForm.color" type="text" class="form-control" placeholder="AP88646" />
        </div>
        <div class="form-group">
          <label>{{ $t('productionBatches.labelCode') }}</label>
          <input v-model="scanForm.code" type="text" class="form-control" placeholder="T6276" />
        </div>
        <div class="form-group">
          <label>{{ $t('productionBatches.labelMachine') }}</label>
          <select v-model="scanMachineId" class="form-select" @change="onScanMachineChange">
            <option :value="null">{{ $t('productionBatches.optSelectMachine') }}</option>
            <option v-for="m in machines" :key="m.id" :value="m.id">{{ m.code }}</option>
          </select>
          <span v-if="scanForm.machine && !scanMachineId" class="text-error scan-hint">
            {{ $t('productionBatches.scanMachineMismatchHint', { machine: scanForm.machine }) }}
          </span>
        </div>
        <div class="form-group">
          <label>{{ $t('productionBatches.labelTank') }}</label>
          <select v-model="scanTankId" class="form-select" :disabled="!scanMachineId">
            <option :value="null">{{ $t('productionBatches.optSelectTank') }}</option>
            <option v-for="t in availableTanks" :key="t.id" :value="t.id">{{ t.code }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>{{ $t('productionBatches.labelWaterLevel') }}</label>
          <select v-model="scanForm.level" class="form-select">
            <option value="">{{ $t('productionBatches.optSelectWaterLevel') }}</option>
            <option v-for="lv in waterLevels" :key="lv" :value="String(lv)">{{ lv }}</option>
          </select>
        </div>
      </div>

      <div class="scan-raw-preview" v-if="scanForm.rawQrDye || scanForm.rawQrChemical">
        <div class="raw-box">
          <label>{{ $t('productionBatches.rawDyeLabel') }}</label>
          <input :value="scanForm.rawQrDye" type="text" class="form-control mono-text" readonly />
        </div>
        <div class="raw-box">
          <label>{{ $t('productionBatches.rawChemLabel') }}</label>
          <input :value="scanForm.rawQrChemical" type="text" class="form-control mono-text" readonly />
        </div>
      </div>

      <!-- Bảng tách dòng RACK/MÃ/KHỐI LƯỢNG — giống layout scaleform.frm (VBA gốc) -->
      <div class="rack-tables-row" v-if="dyeRackLines.length || chemRackLines.length">
        <div class="rack-table-col">
          <label class="rack-table-title">{{ $t('productionBatches.rackDyeTitle') }}</label>
          <table class="data-table rack-table">
            <thead>
              <tr>
                <th>{{ $t('productionBatches.colRack') }}</th>
                <th>{{ $t('productionBatches.colDyeCode') }}</th>
                <th>{{ $t('productionBatches.colWeight') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in dyeRackLines" :key="'dye-' + idx" class="rack-row-filled">
                <td class="rack-cell-num">{{ line.rack }}</td>
                <td class="rack-cell-code">{{ line.code }}</td>
                <td class="rack-cell-weight">{{ line.weight }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="rack-table-col">
          <label class="rack-table-title">{{ $t('productionBatches.rackChemTitle') }}</label>
          <table class="data-table rack-table">
            <thead>
              <tr>
                <th>{{ $t('productionBatches.colRack') }}</th>
                <th>{{ $t('productionBatches.colChemCode') }}</th>
                <th>{{ $t('productionBatches.colWeight') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in chemRackLines" :key="'chem-' + idx" class="rack-row-filled">
                <td class="rack-cell-num">{{ line.rack }}</td>
                <td class="rack-cell-code">{{ line.code }}</td>
                <td class="rack-cell-weight">{{ line.weight }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="scanIncomplete" class="text-error mt-2 scan-incomplete-warning">
        {{ $t('productionBatches.scanIncompleteWarning') }}
      </p>
      <p v-if="scanErrorMsg" class="text-error mt-2">❌ {{ scanErrorMsg }}</p>
      <p v-if="scanSuccessMsg" class="text-success mt-2">✅ {{ scanSuccessMsg }}</p>

      <div v-if="duplicateWarning" class="duplicate-warning-box mt-2">
        <p class="text-error">⚠️ {{ duplicateWarning }}</p>
        <label class="duplicate-confirm-label">
          <input type="checkbox" v-model="confirmDuplicateSave" />
          {{ $t('productionBatches.duplicateConfirmLabel') }}
        </label>
      </div>

      <div class="scan-actions-row">
        <button
          @click="saveScanOrder"
          class="btn btn-primary"
          :disabled="savingScan || scanIncomplete || !scanForm.color || !scanForm.code || !scanMachineId || (!!duplicateWarning && !confirmDuplicateSave)"
        >
          {{ savingScan ? $t('productionBatches.btnSaving') : (duplicateWarning ? $t('productionBatches.btnSaveDuplicateConfirmed') : $t('productionBatches.btnSave')) }}
        </button>
        <button @click="clearScanForm" class="btn btn-secondary">{{ $t('productionBatches.btnClear') }}</button>
        <button
          @click="confirm2Ok = !confirm2Ok"
          class="btn"
          :class="confirm2Ok ? 'btn-success' : 'btn-secondary'"
          :disabled="scanIncomplete"
        >
          {{ confirm2Ok ? $t('productionBatches.btnApprovedOn') : $t('productionBatches.btnApprove') }}
        </button>
        <button @click="checkDuplicateOrder" class="btn btn-secondary" :disabled="!scanForm.color || !scanForm.code">
          {{ $t('productionBatches.btnCheck') }}
        </button>
      </div>
      <p class="text-muted scan-footnote">
        {{ $t('productionBatches.footnote') }}
      </p>
    </section>

    <!-- 30 bản gần nhất — xem nhanh không cần rời trang quét; đầy đủ + lọc/duyệt ở
         /production-batches/list. Dùng chung nguồn `batches` đã fetch cho CHECK trùng
         (đã sắp created_at desc từ backend, chỉ cần cắt N dòng đầu — RECENT_BATCH_LIMIT). -->
    <section class="section card-sec recent-batches-panel">
      <div class="recent-header">
        <h3>{{ $t('productionBatches.recentTitle', { limit: RECENT_BATCH_LIMIT }) }}</h3>
        <router-link to="/production-batches/list" class="text-muted font-sm">{{ $t('productionBatches.viewAllLink') }}</router-link>
      </div>
      <div class="table-container-fixed mt-3">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('productionBatches.colBatchId') }}</th>
              <th>{{ $t('productionBatches.colColor') }}</th>
              <th>{{ $t('productionBatches.colProductCode') }}</th>
              <th>{{ $t('productionBatches.colMachine') }}</th>
              <th>{{ $t('productionBatches.colTank') }}</th>
              <th>{{ $t('productionBatches.colWaterLevelShort') }}</th>
              <th>{{ $t('productionBatches.colProgress') }}</th>
              <th>{{ $t('productionBatches.colStatus') }}</th>
              <th>{{ $t('productionBatches.colUpdated') }}</th>
              <th>{{ $t('productionBatches.colActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="batch in recentBatches" :key="batch.id" @click="openRecentDetail(batch)" class="clickable-row">
              <td class="highlight-code">{{ batch.legacy_batch_id }}</td>
              <td>{{ batch.color }}</td>
              <td>{{ batch.product_code }}</td>
              <td><span class="machine-tag">{{ batch.machine?.code || 'N/A' }}</span></td>
              <td @click.stop>
                <select
                  v-if="editingTankBatchId === batch.id"
                  :value="batch.tank_id || ''"
                  class="form-select table-row-select"
                  @change="updateTank(batch, ($event.target as HTMLSelectElement).value)"
                  @blur="editingTankBatchId = null"
                >
                  <option value="">{{ $t('productionBatches.optTankNone') }}</option>
                  <option v-for="t in tanks.filter(t => t.machine_id === batch.machine_id)" :key="t.id" :value="t.id">
                    {{ t.code }}
                  </option>
                </select>
                <button v-else-if="batch.status === 'NEW'" class="tank-pick-btn" @click="editingTankBatchId = batch.id" :title="$t('productionBatches.tankPickTitle')">
                  <span class="tank-tag" v-if="batch.tank">{{ batch.tank?.code }}</span>
                  <span class="text-muted" v-else>{{ $t('productionBatches.tankPickPlaceholder') }}</span>
                </button>
                <span v-else class="tank-tag" :title="$t('productionBatches.tankLockedTitle')">
                  {{ batch.tank?.code || 'N/A' }}
                </span>
              </td>
              <td>{{ batch.level_code || 'N/A' }}</td>
              <td>
                <div class="progress-bar-wrapper" :title="$t('productionBatches.progressTitle', { percent: getProgressPercent(batch.status) })">
                  <div class="progress-bar-fill" :style="{ width: getProgressPercent(batch.status) + '%' }"></div>
                </div>
              </td>
              <td><span :class="['badge', getRecentStatusClass(batch.status)]">{{ batch.status }}</span></td>
              <td class="date-cell">{{ formatRecentDate(batch.updated_at || batch.created_at) }}</td>
              <td @click.stop class="actions-cell">
                <button
                  @click="openRecentDetail(batch)"
                  class="btn btn-secondary btn-sm"
                  :title="$t('productionBatches.detailBtnTitle')"
                >
                  {{ $t('productionBatches.detailBtnLabel') }}
                </button>
                <button
                  v-if="batch.status === 'NEW'"
                  @click="approveRecentBatch(batch)"
                  class="btn btn-primary btn-sm"
                  :disabled="approvingRecentId === batch.id || !batch.tank"
                  :title="!batch.tank ? $t('productionBatches.approveTankRequiredTitle') : ''"
                >
                  {{ approvingRecentId === batch.id ? $t('productionBatches.approvingLabel') : (!batch.tank ? $t('productionBatches.approveMissingTank') : $t('productionBatches.approveLabel')) }}
                </button>
              </td>
            </tr>
            <tr v-if="recentBatches.length === 0">
              <td colspan="10" class="text-center text-muted pad-empty-row">{{ $t('productionBatches.emptyRecentBatches') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Chi tiết Lô sản xuất — giống hệt modal ở /production-batches/list, xem nhanh
         DYE/CHEM + duyệt ngay tại đây không cần rời trang quét. -->
    <div class="drawer-overlay center-modal-overlay" v-if="recentDetailOpen" @click.self="closeRecentDetail">
      <div class="right-drawer detail-modal">
        <div class="drawer-header">
          <h3>{{ $t('productionBatches.detailModalTitle') }}</h3>
          <button @click="closeRecentDetail" class="drawer-close-btn">&times;</button>
        </div>

        <div class="drawer-body" v-if="selectedRecentBatch">
          <div class="detail-hero mb-4">
            <span :class="['badge', getRecentStatusClass(selectedRecentBatch.status)]" class="hero-badge">
              {{ selectedRecentBatch.status }}
            </span>
            <h4>{{ $t('productionBatches.detailBatchHeading', { batchId: selectedRecentBatch.legacy_batch_id }) }}</h4>
          </div>

          <div class="detail-info-list">
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailColorLabel') }}</span>
              <span class="detail-value">{{ selectedRecentBatch.color }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailProductLabel') }}</span>
              <span class="detail-value">{{ selectedRecentBatch.product_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailMachineLabel') }}</span>
              <span class="detail-value">{{ selectedRecentBatch.machine ? selectedRecentBatch.machine.code + ' - ' + selectedRecentBatch.machine.name : 'N/A' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailTankLabel') }}</span>
              <select
                v-if="selectedRecentBatch.status === 'NEW'"
                :value="selectedRecentBatch.tank_id || ''"
                class="form-select detail-tank-select"
                @change="updateTank(selectedRecentBatch, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">{{ $t('productionBatches.optTankNotSelected') }}</option>
                <option v-for="t in tanks.filter(t => t.machine_id === selectedRecentBatch.machine_id)" :key="t.id" :value="t.id">
                  {{ $t('productionBatches.tankPrefixed', { code: t.code }) }}
                </option>
              </select>
              <span v-else class="detail-value" :title="$t('productionBatches.tankLockedTitle')">
                {{ selectedRecentBatch.tank ? $t('productionBatches.tankPrefixed', { code: selectedRecentBatch.tank.code }) : $t('productionBatches.tankNotSelected') }}
              </span>
            </div>
            <div class="detail-item" v-if="selectedRecentBatch.level_code">
              <span class="detail-label">{{ $t('productionBatches.detailWaterLevelLabel') }}</span>
              <span class="detail-value">{{ selectedRecentBatch.level_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailCreatedLabel') }}</span>
              <span class="detail-value">{{ formatRecentDate(selectedRecentBatch.created_at) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('productionBatches.detailUpdatedLabel') }}</span>
              <span class="detail-value">{{ formatRecentDate(selectedRecentBatch.updated_at) }}</span>
            </div>
          </div>

          <!-- Bảng tách dòng RACK/MÃ/KHỐI LƯỢNG — giống layout scaleform.frm (VBA gốc) -->
          <div class="rack-tables-row mt-4" v-if="selectedRecentDyeLines.length || selectedRecentChemLines.length">
            <div class="rack-table-col">
              <label class="rack-table-title">{{ $t('productionBatches.rackDyeTitle') }}</label>
              <table class="data-table rack-table">
                <thead><tr><th>{{ $t('productionBatches.colRack') }}</th><th>{{ $t('productionBatches.colDyeCode') }}</th><th>{{ $t('productionBatches.colWeight') }}</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedRecentDyeLines" :key="'rec-dye-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="rack-table-col">
              <label class="rack-table-title">{{ $t('productionBatches.rackChemTitle') }}</label>
              <table class="data-table rack-table">
                <thead><tr><th>{{ $t('productionBatches.colRack') }}</th><th>{{ $t('productionBatches.colChemCode') }}</th><th>{{ $t('productionBatches.colWeight') }}</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedRecentChemLines" :key="'rec-chem-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-else class="text-muted font-sm mt-4">{{ $t('productionBatches.noRackData') }}</p>

          <p v-if="selectedRecentBatch.status === 'NEW' && !selectedRecentBatch.tank" class="text-error mt-2">
            {{ $t('productionBatches.tankRequiredWarning') }}
          </p>

          <div class="drawer-actions mt-4">
            <button
              v-if="selectedRecentBatch.status === 'NEW'"
              @click="approveRecentBatch(selectedRecentBatch)"
              class="btn btn-primary flex-2"
              :disabled="approvingRecentId === selectedRecentBatch.id || !selectedRecentBatch.tank"
              :title="!selectedRecentBatch.tank ? $t('productionBatches.approveTankRequiredTitle') : ''"
            >
              {{ approvingRecentId === selectedRecentBatch.id ? $t('productionBatches.approvingLabel') : $t('productionBatches.approveCta') }}
            </button>
            <button @click="closeRecentDetail" class="btn btn-secondary flex-1">{{ $t('productionBatches.closeWindow') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import SvgIcon from '../components/SvgIcon.vue';
import { currentWorkstation } from '../services/workstation';
import { parseRackLines } from '../utils/rackParser';
import echo from '../services/echo';

const { t } = useI18n({ useScope: 'global' });

// Danh sách lô gần đây — dùng làm nguồn cho CHECK trùng màu/mã hàng trước khi SAVE
// (checkDuplicateOrder) VÀ hiển thị nhanh N dòng mới nhất cuối trang (recentBatches).
// Bảng đầy đủ (lọc/duyệt) vẫn ở /production-batches/list.
// 10 -> 30 (yêu cầu 2026-07-31): lô đã in xong ở /print-station bị các lô mới quét sau đó
// đẩy khỏi top 10 quá nhanh, người vận hành không tra lại được. Phải truyền per_page=30
// cho API (mặc định backend chỉ trả 15/trang) chứ không chỉ nới slice ở client.
const RECENT_BATCH_LIMIT = 30;
const batches = ref<any[]>([]);
// Lô đã CONFIRMED bên /print-station (đã bấm "✅ OK" — đã in xong, rơi xuống bảng
// lịch sử bên đó) không cần hiện lại ở đây nữa (yêu cầu 2026-07-31) — nhận biết qua
// machine_dispatches.queue_state (batch.dispatches do backend eager-load kèm theo,
// xem ProductionBatchController::index), KHÔNG dùng batch.status vì trạng thái lô
// độc lập với trạng thái in (xem điều tra lô ADHOC cùng ngày).
const isPrintConfirmed = (batch: any): boolean =>
  Array.isArray(batch.dispatches) && batch.dispatches.some((d: any) => d.queue_state === 'CONFIRMED');

// Chưa duyệt (NEW) lên đầu, đã duyệt (mọi status khác) đẩy xuống dưới (yêu cầu
// 2026-07-31) — sort ổn định (Array.sort đảm bảo stable từ ES2019) nên trong từng
// nhóm vẫn giữ nguyên thứ tự created_at desc đã có sẵn từ backend.
const recentBatches = computed(() =>
  [...batches.value]
    .filter(b => !isPrintConfirmed(b))
    .sort((a, b) => (a.status === 'NEW' ? 0 : 1) - (b.status === 'NEW' ? 0 : 1))
    .slice(0, RECENT_BATCH_LIMIT)
);

// Modal "Chi tiết Lô sản xuất" — giống hệt /production-batches/list, xem DYE/CHEM +
// duyệt ngay không cần rời trang quét (yêu cầu 2026-07-24).
const recentDetailOpen = ref(false);
const selectedRecentBatch = ref<any | null>(null);
const selectedRecentDyeLines = computed(() => parseRackLines(selectedRecentBatch.value?.raw_qr_dye));
const selectedRecentChemLines = computed(() => parseRackLines(selectedRecentBatch.value?.raw_qr_chemical));

const lockBodyScroll = () => { document.body.style.overflow = 'hidden'; };
const unlockBodyScroll = () => { document.body.style.overflow = ''; };

const openRecentDetail = (batch: any) => {
  selectedRecentBatch.value = batch;
  recentDetailOpen.value = true;
  lockBodyScroll();
};
const closeRecentDetail = () => {
  recentDetailOpen.value = false;
  selectedRecentBatch.value = null;
  unlockBodyScroll();
};

const getProgressPercent = (status: string) => {
  const mapping: Record<string, number> = {
    'NEW': 10,
    'APPROVED': 25,
    'READY_TO_WEIGH': 40,
    'WEIGHING': 60,
    'WEIGHED': 80,
    'SENT': 92,
    'DONE': 100
  };
  return mapping[status] || 0;
};

// Chọn nhanh Thùng trộn + Duyệt ngay trong bảng "lô gần nhất" — cùng cơ chế với
// /production-batches/list (duyệt giờ bắt buộc có thùng, xem ApproveProductionOrderService).
const editingTankBatchId = ref<string | null>(null);
const approvingRecentId = ref<string | null>(null);

const updateTank = async (batch: any, tankIdRaw: string) => {
  const tankId = tankIdRaw === '' ? null : Number(tankIdRaw);
  editingTankBatchId.value = null;
  try {
    const res = await axios.put(`/api/production-batches/${batch.id}/tank`, { tank_id: tankId });
    batch.tank_id = res.data.data.tank_id;
    batch.tank = res.data.data.tank;
  } catch (error: any) {
    alert(error.response?.data?.message || t('productionBatches.errUpdateTank'));
  }
};

const approveRecentBatch = async (batch: any) => {
  approvingRecentId.value = batch.id;
  try {
    await axios.post(`/api/production-batches/${batch.id}/approve`, {
      workstation_id: currentWorkstation.value?.code
    });
    batch.status = 'APPROVED';
  } catch (error: any) {
    alert(error.response?.data?.message || t('productionBatches.errApproveBatch'));
  } finally {
    approvingRecentId.value = null;
  }
};

// Metadata lists
const machines = ref<any[]>([]);
const tanks = ref<any[]>([]);

// Mức nước cố định (Water Level) - 4 giá trị chuẩn hệ thống
const waterLevels = [50, 100, 250, 450];

// Trạm quét đơn thật (WS-ORDER-01) — state khớp Box1/Box2/Box4/Box5/Box6/Box7 (mainform.frm)
const scanInputRef = ref<HTMLInputElement | null>(null);
const scanForm = reactive({
  color: '',
  code: '',
  machine: '',
  level: '',
  rawQrDye: '',
  rawQrChemical: ''
});
const scanMachineId = ref<number | null>(null);
const scanTankId = ref<number | null>(null);

// Bảng RACK / MÃ / KHỐI LƯỢNG hiển thị tách dòng như scaleform.frm (VBA gốc) — chỉ hiển
// thị lại (đọc), KHÔNG thay thế raw_qr_dye/raw_qr_chemical đã lưu nguyên văn xuống DB.
const dyeRackLines = computed(() => parseRackLines(scanForm.rawQrDye));
const chemRackLines = computed(() => parseRackLines(scanForm.rawQrChemical));
const confirm2Ok = ref(false);
const savingScan = ref(false);
const scanErrorMsg = ref('');
const scanSuccessMsg = ref('');
// Cảnh báo nghi trùng (409 DUPLICATE_WARNING từ backend) — không chặn cứng, chỉ hiện
// cảnh báo + checkbox; tick xác nhận rồi bấm SAVE lại mới thật sự lưu (confirm_duplicate=true).
const duplicateWarning = ref('');
const confirmDuplicateSave = ref(false);
// Chặn quét chồng lấn: nếu quét lần 2 trước khi lần 1 xử lý xong, 2 request scan-parse
// chạy song song — request nào TRẢ VỀ SAU sẽ ghi đè scanForm bất kể lần quét nào mới hơn
// (race điều kiện phản hồi mạng), tạo cảm giác "quét nhiều lần ra kết quả khác nhau" dù
// backend tách chuỗi hoàn toàn ổn định (đã xác nhận: gọi lại 5 lần cùng input ra kết quả
// giống hệt). scanning=true khóa mọi lần quét/bấm mới cho tới khi lần trước xử lý xong.
const scanning = ref(false);
// Debug tạm thời (2026-07-19): hiện lại đúng chuỗi raw_scan mà server nhận được, để
// đối chiếu với QR gốc khi máy quét thật đọc ra kết quả không như mong đợi — xóa khi
// đã xác định xong nguyên nhân lệch dữ liệu.
const debugRawScan = ref('');
// Cảnh báo quét bị rớt ký tự giữa chừng (2026-07-19) — xem QrPayloadService::
// parseOrderEntryScan (scan_looks_incomplete). Chặn SAVE/PHÊ DUYỆT khi đang bật để
// không lặp lại lỗi lưu nhầm dữ liệu như batch LEP70158 trước đây.
const scanIncomplete = ref(false);

const availableTanks = computed(() => tanks.value.filter(t => t.machine_id === scanMachineId.value));

/** "VD" & Format(Val(Mid(s,3)),"000") — khớp QrPayloadService::normalizeVdCode (VBA: VD2 -> VD002). */
const normalizeVdCode = (code: string): string => {
  const upper = code.toUpperCase().trim();
  if (upper.startsWith('VD')) {
    const num = parseInt(upper.slice(2), 10) || 0;
    return 'VD' + String(num).padStart(3, '0');
  }
  return upper;
};

/** Dạng 2 chữ số VD01-VD18 — đúng arrVD của VBA gốc, cũng là mã trong danh mục máy. */
const shortVdCode = (code: string): string => {
  const upper = code.toUpperCase().trim();
  if (!upper.startsWith('VD')) return upper;
  return 'VD' + String(parseInt(upper.slice(2), 10) || 0).padStart(2, '0');
};

// Thử nguyên văn -> 3 chữ số -> 2 chữ số: QR ngoài xưởng bắn mã 2 chữ số nhưng vẫn có nguồn
// ghi 3 chữ số, còn danh mục máy nay là 2 chữ số. Thiếu bước lui, một trong hai dạng tra trượt.
const resolveMachineIdFromCode = (code: string): number | null => {
  if (!code) return null;
  const upper = code.toUpperCase().trim();
  const timKiem = (c: string) => machines.value.find(m => m.code.toUpperCase() === c);
  const match = timKiem(upper) || timKiem(normalizeVdCode(upper)) || timKiem(shortVdCode(upper));
  return match ? match.id : null;
};

const onScanMachineChange = () => {
  // Đổi máy tay -> reset thùng vì danh sách thùng phụ thuộc máy.
  scanTankId.value = null;
};

const handleScan = async () => {
  // Đọc thẳng .value từ DOM (input KHÔNG dùng v-model — xem ghi chú ở template):
  // máy quét QR kiểu "giả bàn phím" bắn ~100 ký tự trong vài chục mili-giây; nếu ô
  // này là v-model (Vue re-render toàn trang sau MỖI ký tự), trình duyệt không xử lý
  // kịp và RỚT MẤT một đoạn ký tự giữa chừng (đã xác minh thực tế: quét phiếu
  // LEP70158 ra "...GSE5430550-256..." thay vì "...SE5433-VD03-50-dye-73-...", mất
  // hẳn đoạn giữa). Input để trình duyệt tự xử lý gõ phím (uncontrolled), chỉ đọc
  // giá trị 1 lần khi Enter — không có re-render nào xảy ra trong lúc quét.
  const el = scanInputRef.value;
  const rawValueAtScanTime = (el?.value ?? '').trim();
  if (!rawValueAtScanTime || scanning.value) return; // chặn bấm/quét chồng khi đang xử lý lần trước

  scanning.value = true;
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';
  // Reset trạng thái cảnh báo trùng của lượt quét TRƯỚC — nếu không, "vẫn lưu" đã tick
  // (confirm_duplicate=true) cho đơn cũ sẽ bị mang sang đơn mới quét, khiến Save đơn mới
  // (dù thật sự trùng) lưu thẳng không hề cảnh báo. Phát hiện 2026-07-24: quét đơn A bị
  // cảnh báo trùng, tick "vẫn lưu", rồi quét tiếp đơn B (khác hẳn) mà không Save đơn A.
  duplicateWarning.value = '';
  confirmDuplicateSave.value = false;
  if (el) el.value = ''; // xóa ngay để lần quét kế tiếp (nếu có) không bị nối vào

  try {
    const res = await axios.post('/api/production-batches/scan-parse', { raw_scan: rawValueAtScanTime });
    const d = res.data.data;
    scanForm.color = d.color;
    scanForm.code = d.code;
    scanForm.machine = d.machine;
    scanForm.level = d.level;
    scanForm.rawQrDye = d.raw_qr_dye;
    scanForm.rawQrChemical = d.raw_qr_chemical;
    debugRawScan.value = d.debug_raw_scan || '';
    scanIncomplete.value = !!d.scan_looks_incomplete;

    scanMachineId.value = resolveMachineIdFromCode(d.machine);
    scanTankId.value = null;
    autoCheckDuplicateAfterScan();
  } catch (error: any) {
    scanErrorMsg.value = error.response?.data?.message || t('productionBatches.errScanRead');
  } finally {
    scanning.value = false;
    // Input bị disabled trong lúc xử lý nên mất focus — trả focus lại để quét liên
    // tiếp không cần thao tác viên tự bấm lại vào ô.
    nextTick(() => scanInputRef.value?.focus());
  }
};

const clearScanForm = () => {
  if (scanInputRef.value) scanInputRef.value.value = '';
  scanForm.color = '';
  scanForm.code = '';
  scanForm.machine = '';
  scanForm.level = '';
  scanForm.rawQrDye = '';
  scanForm.rawQrChemical = '';
  debugRawScan.value = '';
  scanIncomplete.value = false;
  scanMachineId.value = null;
  scanTankId.value = null;
  confirm2Ok.value = false;
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';
  duplicateWarning.value = '';
  confirmDuplicateSave.value = false;
  scanInputRef.value?.focus();
};

// Trùng màu+mã hàng+máy với 1 đơn đang chờ duyệt (status NEW) — dùng chung logic chặn
// trùng phía backend (store()), chỉ tính trùng với đơn CHƯA duyệt vì đơn đã duyệt/gửi
// máy coi như đã "rời" khỏi hàng chờ nhập (y hệt MoveToSend xóa khỏi tbl_input_all).
const isDuplicateOrder = (color: string, code: string, machineId: number | null) => {
  return batches.value.some(
    b => b.status === 'NEW' && b.color === color && b.product_code === code && b.machine_id === machineId
  );
};

// Tự động cảnh báo NGAY sau khi quét xong (yêu cầu 2026-07-24: không đợi tới lúc bấm
// Save mới báo) — hiện đúng banner ⚠️ + tick "vẫn lưu" giống hệt luồng Save phát hiện
// trùng phía backend, để người vận hành biết ngay khi vừa quét, không quét/nhập tiếp
// đơn kế mà không hay đơn này đang trùng.
const autoCheckDuplicateAfterScan = () => {
  if (isDuplicateOrder(scanForm.color, scanForm.code, scanMachineId.value)) {
    duplicateWarning.value = t('productionBatches.duplicateAutoWarning');
  }
};

// CHECK (btnCheck_Click trong checkform.frm) — kiểm tra trùng màu+mã hàng+máy TRƯỚC khi
// Save, dùng chung logic chặn trùng với store() (chỉ tính đơn đang NEW). Máy cũng tính
// vào điều kiện trùng vì cùng màu+mã hàng chạy ở 2 máy khác nhau là hợp lệ.
const checkDuplicateOrder = async () => {
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';
  const duplicate = isDuplicateOrder(scanForm.color, scanForm.code, scanMachineId.value);
  if (duplicate) {
    scanSuccessMsg.value = t('productionBatches.checkResultYes');
  } else {
    scanSuccessMsg.value = t('productionBatches.checkResultNo');
  }
};

// SAVE (btnSAVE_Click): tạo đơn; nếu đã chọn Thùng + đã PHÊ DUYỆT -> duyệt ngay trong
// cùng thao tác (đúng VBA: "If confirm2=OK And tank<>"" Then MoveToSend").
const saveScanOrder = async () => {
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';

  if (!scanForm.color || !scanForm.code || !scanMachineId.value) {
    scanErrorMsg.value = t('productionBatches.errMissingInfo');
    return;
  }

  savingScan.value = true;
  try {
    const createRes = await axios.post('/api/production-batches', {
      legacy_batch_id: `${scanForm.color}-${scanForm.code}-${Date.now()}`,
      color: scanForm.color,
      product_code: scanForm.code,
      machine_id: scanMachineId.value,
      tank_id: scanTankId.value || null,
      level_code: scanForm.level || null,
      raw_qr_dye: scanForm.rawQrDye || null,
      raw_qr_chemical: scanForm.rawQrChemical || null,
      confirm_duplicate: confirmDuplicateSave.value
    });

    const newBatch = createRes.data.data;
    duplicateWarning.value = '';
    confirmDuplicateSave.value = false;

    // Tick PHÊ DUYỆT là đủ để tự động duyệt ngay trong cùng thao tác SAVE.
    if (confirm2Ok.value) {
      try {
        await axios.post(`/api/production-batches/${newBatch.id}/approve`, {
          workstation_id: currentWorkstation.value?.code
        });
        scanSuccessMsg.value = t('productionBatches.msgSentToPrintQueue', { color: scanForm.color, code: scanForm.code });
      } catch (approveErr: any) {
        // Lưu đơn đã thành công — chỉ riêng bước duyệt tự động thất bại (vd chưa chọn
        // Thùng trộn). Báo rõ để người vận hành tự duyệt lại thủ công trong bảng bên dưới,
        // không lặng lẽ nuốt lỗi.
        scanErrorMsg.value = t('productionBatches.msgAutoApproveFailed', {
          error: approveErr.response?.data?.message || t('productionBatches.errUnknownFallback')
        });
      }
    } else {
      scanSuccessMsg.value = t('productionBatches.msgSavedPending', { color: scanForm.color, code: scanForm.code });
    }

    clearScanForm();
    fetchBatches();
  } catch (error: any) {
    if (error.response?.data?.status === 'DUPLICATE_WARNING') {
      duplicateWarning.value = error.response.data.message;
    } else {
      scanErrorMsg.value = error.response?.data?.message || t('productionBatches.errSaveGeneric');
    }
  } finally {
    savingScan.value = false;
  }
};

let batchPollInterval: any = null;

onMounted(() => {
  fetchBatches();
  fetchMetadata();
  scanInputRef.value?.focus();

  // Realtime qua Reverb — bất kỳ lô nào được tạo/duyệt/đổi trạng thái ở nơi khác (màn
  // hình này, /production-batches/list, hay trạm cân) đều làm mới ngay danh sách "10 lô
  // gần nhất" + nguồn CHECK trùng ở đây, không cần đợi polling hay F5 (yêu cầu 2026-07-23).
  // Xem ProductionBatchUpdated::broadcastOn (backend).
  echo.channel('production-batches').listen('.updated', fetchBatches);

  // Polling làm lưới an toàn nếu WebSocket rớt kết nối tạm thời.
  batchPollInterval = setInterval(fetchBatches, 15000);
});

onUnmounted(() => {
  if (batchPollInterval) clearInterval(batchPollInterval);
  echo.leaveChannel('production-batches');
});

const fetchBatches = async () => {
  try {
    const response = await axios.get('/api/production-batches', {
      params: { per_page: RECENT_BATCH_LIMIT },
    });
    batches.value = response.data.data;
  } catch (error) {
    console.error('Error fetching batches:', error);
  }
};

const fetchMetadata = async () => {
  try {
    const [machinesRes, tanksRes] = await Promise.all([
      axios.get('/api/machines'),
      axios.get('/api/tanks')
    ]);
    machines.value = machinesRes.data.data || [];
    tanks.value = tanksRes.data.data || [];
  } catch (error) {
    console.error('Error fetching metadata:', error);
  }
};

const getRecentStatusClass = (status: string) => {
  const mapping: Record<string, string> = {
    'NEW': 'badge-grey',
    'APPROVED': 'badge-blue',
    'READY_TO_WEIGH': 'badge-blue',
    'WEIGHING': 'badge-yellow',
    'WEIGHED': 'badge-green',
    'SENT': 'badge-orange',
    'DONE': 'badge-purple'
  };
  return mapping[status] || 'badge-grey';
};

const formatRecentDate = (dateStr: string) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleString('vi-VN', { hour12: false });
};
</script>

<style scoped>
.batches-view-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.page-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-lg);
}

.header-titles h2 {
  font-size: 1.6rem;
  color: var(--text-title);
  margin-bottom: 4px;
}

/* Trạm quét đơn thật (WS-ORDER-01) */
.scan-order-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  border: 2px solid var(--primary);
}

.scan-panel-header h3 {
  margin: 0 0 4px 0;
  font-size: 1.2rem;
  color: var(--text-title);
}

.scan-input-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.scan-input-row label {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.scan-input-large {
  font-size: 1.3rem;
  padding: 14px 16px;
  font-family: monospace;
  border: 2px dashed var(--primary);
}

.scan-fields-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--space-lg);
}

.scan-hint {
  font-size: 11px;
  margin-top: 4px;
  display: block;
}

.scan-raw-preview {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-lg);
}

.raw-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.raw-box label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.scan-debug-line {
  font-size: 0.78rem;
  word-break: break-all;
  margin: 0.25rem 0 0.5rem;
}

.scan-incomplete-warning {
  font-weight: 600;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-error, #e53e3e);
  border-radius: 6px;
  background-color: rgba(229, 62, 62, 0.08);
}

.duplicate-warning-box {
  padding: var(--space-md) var(--space-lg);
  border: 1px solid var(--status-yellow-border);
  border-radius: var(--radius-md);
  background-color: var(--status-yellow-bg);
}
.duplicate-warning-box p {
  margin: 0 0 8px 0;
  color: var(--status-yellow);
  font-weight: 600;
}
.duplicate-confirm-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: var(--text-body);
  cursor: pointer;
}
.duplicate-confirm-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--primary);
  cursor: pointer;
}

.mono-text {
  font-family: monospace;
  font-size: 0.8rem;
  background-color: var(--bg-main);
}

.scan-actions-row {
  display: flex;
  gap: var(--space-lg);
  flex-wrap: wrap;
}

.btn-success {
  background-color: var(--status-green);
  color: var(--text-white);
  border-color: var(--status-green);
}

.scan-footnote {
  font-size: 0.8rem;
}

@media (max-width: 768px) {
  .scan-raw-preview {
    grid-template-columns: 1fr;
  }
}

/* Bảng RACK/MÃ/KHỐI LƯỢNG tách dòng — layout kiểu scaleform.frm (VBA gốc) */
.rack-tables-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-lg);
  margin-top: var(--space-lg);
}

.rack-table-title {
  display: block;
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.rack-table th {
  font-size: 11px;
}

.rack-row-filled td {
  background-color: var(--status-green-bg);
}

.rack-cell-num {
  width: 60px;
  font-weight: 700;
  color: var(--text-muted);
}

.rack-cell-code {
  font-weight: 700;
  font-family: monospace;
}

.rack-cell-weight {
  font-weight: 700;
  font-family: monospace;
  color: var(--status-blue);
}

@media (max-width: 768px) {
  .rack-tables-row {
    grid-template-columns: 1fr;
  }
}

/* Bảng lô gần nhất */
.recent-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.recent-header h3 {
  margin: 0;
  font-size: 1.05rem;
  color: var(--text-title);
}

.table-container-fixed {
  width: 100%;
  overflow-x: auto;
  border-radius: var(--radius-lg);
}

.highlight-code {
  color: var(--primary-hover);
  font-family: monospace;
}

.machine-tag {
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card);
  padding: 3px var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
}

.date-cell {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.pad-empty-row {
  padding: var(--space-4xl) 0 !important;
}

.tank-tag {
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card);
  padding: 3px var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
}

.tank-pick-btn {
  background: none;
  border: 1px dashed var(--border-card);
  border-radius: var(--radius-sm);
  padding: 3px var(--space-sm);
  cursor: pointer;
  font-size: 12px;
  color: var(--text-muted);
}

.tank-pick-btn:hover {
  border-color: var(--primary);
  color: var(--primary-hover);
  background-color: var(--bg-card-hover);
}

.table-row-select {
  height: 32px;
  padding: 0 var(--space-sm);
  font-size: 12px;
  width: auto;
}

.clickable-row {
  cursor: pointer;
}

.detail-tank-select {
  width: auto;
  min-width: 140px;
}

.progress-bar-wrapper {
  width: 100px;
  height: 6px;
  background-color: var(--border-card);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
  border-radius: var(--radius-full);
  transition: width 0.3s ease;
}

/* Modal "Chi tiết Lô sản xuất" — port từ /production-batches/list để 2 trang hiển thị
   giống hệt nhau khi xem chi tiết 1 lô (yêu cầu 2026-07-24). */
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  justify-content: flex-end;
}

.right-drawer {
  width: 100%;
  max-width: 460px;
  height: 100%;
  background-color: var(--bg-card);
  border-left: 1px solid var(--border-card);
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  animation: slideLeft 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideLeft {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

.center-modal-overlay {
  align-items: center;
  justify-content: center;
  padding: var(--space-2xl);
}

.detail-modal {
  max-width: 900px;
  width: 100%;
  height: auto;
  max-height: 90vh;
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  animation: popCenter 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes popCenter {
  from { transform: scale(0.96); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

@media (max-width: 768px) {
  .center-modal-overlay {
    padding: 0;
  }
  .detail-modal {
    max-width: 100%;
    height: 100%;
    max-height: 100%;
    border-radius: 0;
  }
}

.drawer-header {
  height: 70px;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
}

.drawer-close-btn {
  font-size: 2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
}

.drawer-close-btn:hover {
  color: var(--text-title);
}

.drawer-body {
  flex: 1;
  padding: var(--space-2xl);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.drawer-actions {
  display: flex;
  gap: var(--space-lg);
}

.detail-hero {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.hero-badge {
  font-size: 12px;
}

.detail-info-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  background-color: var(--bg-main);
  padding: var(--space-xl);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: var(--space-sm);
}

.detail-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.detail-label {
  font-size: 12px;
  color: var(--text-muted);
}

.detail-value {
  font-weight: 600;
  color: var(--text-title);
}

/* Layout adjustments on narrow displays */
@media (max-width: 768px) {
  .page-header-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
