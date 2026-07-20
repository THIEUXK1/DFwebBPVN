<template>
  <div class="batches-view-container">
    <!-- Page Header -->
    <div class="page-header-row">
      <div class="header-titles">
        <h2>📦 Quản lý Lô sản xuất</h2>
        <p class="text-muted">Giám sát trạng thái sản xuất, cấp liệu, điều phối và liên kết kế hoạch từ hệ thống MES.</p>
      </div>
      <div class="header-actions">
        <!-- MES Mock tool trigger button -->
        <button 
          v-if="authStore.isAdmin || authStore.isOperator"
          @click="openCreateDrawer" 
          class="btn btn-primary"
        >
          <SvgIcon name="plus" size="18" />
          Tạo lô từ MES (Giả lập)
        </button>
      </div>
    </div>

    <!-- Trạm quét đơn thật (WS-ORDER-01) — port đúng MainForm VBA "2.C3 grid load row
         lock id FB -192(QR).xlsm": quét QR "ALL DATA" từ MES vào 1 ô duy nhất, hệ thống
         tự tách màu/mã hàng/máy/mức nước, người vận hành chọn Thùng rồi PHÊ DUYỆT + SAVE.
         Xác nhận bằng 2 mẫu QR thật (phiếu MES BEST PACIFIC + ảnh chụp MainForm đang chạy). -->
    <section class="section card-sec scan-order-panel mb-4">
      <div class="scan-panel-header">
        <h3>🔫 Quét đơn sản xuất (MES QR)</h3>
        <span class="text-muted">Quét mã "ALL DATA" trên phiếu MES vào ô bên dưới — hệ thống tự tách thông tin.</span>
      </div>

      <div class="scan-input-row">
        <label>SCAN QR</label>
        <input
          ref="scanInputRef"
          @keyup.enter="handleScan"
          type="text"
          autocomplete="off"
          :disabled="scanning"
          :placeholder="scanning ? 'Đang xử lý mã vừa quét...' : 'Đưa con trỏ vào đây rồi quét mã QR trên phiếu MES...'"
          class="form-control scan-input-large"
        />
      </div>

      <p v-if="debugRawScan" class="text-muted mono-text scan-debug-line">
        🔍 Debug raw_scan nhận được ở server: "{{ debugRawScan }}"
      </p>

      <div class="scan-fields-grid">
        <div class="form-group">
          <label>Mã màu (Color)</label>
          <input v-model="scanForm.color" type="text" class="form-control" placeholder="AP88646" />
        </div>
        <div class="form-group">
          <label>Mã hàng (Code)</label>
          <input v-model="scanForm.code" type="text" class="form-control" placeholder="T6276" />
        </div>
        <div class="form-group">
          <label>MACHINE</label>
          <select v-model="scanMachineId" class="form-select" @change="onScanMachineChange">
            <option :value="null">-- Chọn máy --</option>
            <option v-for="m in machines" :key="m.id" :value="m.id">{{ m.code }}</option>
          </select>
          <span v-if="scanForm.machine && !scanMachineId" class="text-error scan-hint">
            Quét ra "{{ scanForm.machine }}" — không khớp máy nào, chọn tay ở trên.
          </span>
        </div>
        <div class="form-group">
          <label>TANK</label>
          <select v-model="scanTankId" class="form-select" :disabled="!scanMachineId">
            <option :value="null">-- Chọn thùng --</option>
            <option v-for="t in availableTanks" :key="t.id" :value="t.id">{{ t.code }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>OK (Mức nước)</label>
          <input v-model="scanForm.level" type="text" class="form-control" placeholder="450" />
        </div>
      </div>

      <div class="scan-raw-preview" v-if="scanForm.rawQrDye || scanForm.rawQrChemical">
        <div class="raw-box">
          <label>Dữ liệu cân thuốc nhuộm (raw_qr_dye)</label>
          <input :value="scanForm.rawQrDye" type="text" class="form-control mono-text" readonly />
        </div>
        <div class="raw-box">
          <label>Dữ liệu cân hóa chất (raw_qr_chem)</label>
          <input :value="scanForm.rawQrChemical" type="text" class="form-control mono-text" readonly />
        </div>
      </div>

      <!-- Bảng tách dòng RACK/MÃ/KHỐI LƯỢNG — giống layout scaleform.frm (VBA gốc) -->
      <div class="rack-tables-row" v-if="dyeRackLines.length || chemRackLines.length">
        <div class="rack-table-col">
          <label class="rack-table-title">🧵 Thuốc nhuộm (DYE)</label>
          <table class="data-table rack-table">
            <thead>
              <tr>
                <th>RACK</th>
                <th>MÃ THUỐC NHUỘM</th>
                <th>KHỐI LƯỢNG</th>
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
          <label class="rack-table-title">🧪 Hóa chất (CHEM)</label>
          <table class="data-table rack-table">
            <thead>
              <tr>
                <th>RACK</th>
                <th>MÃ HÓA CHẤT</th>
                <th>KHỐI LƯỢNG</th>
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
        ⚠️ Mã quét có dấu hiệu bị rớt ký tự giữa chừng (thiếu đoạn thuốc nhuộm/hóa chất) — dữ liệu bên dưới KHÔNG đáng tin.
        Vui lòng đưa lại phiếu MES và quét lại, không SAVE khi còn cảnh báo này.
      </p>
      <p v-if="scanErrorMsg" class="text-error mt-2">❌ {{ scanErrorMsg }}</p>
      <p v-if="scanSuccessMsg" class="text-success mt-2">✅ {{ scanSuccessMsg }}</p>

      <div v-if="duplicateWarning" class="duplicate-warning-box mt-2">
        <p class="text-error">⚠️ {{ duplicateWarning }}</p>
        <label class="duplicate-confirm-label">
          <input type="checkbox" v-model="confirmDuplicateSave" />
          Tôi xác nhận vẫn muốn lưu đơn này (biết rõ có thể trùng)
        </label>
      </div>

      <div class="scan-actions-row">
        <button
          @click="saveScanOrder"
          class="btn btn-primary"
          :disabled="savingScan || scanIncomplete || !scanForm.color || !scanForm.code || !scanMachineId || (!!duplicateWarning && !confirmDuplicateSave)"
        >
          {{ savingScan ? 'Đang lưu...' : (duplicateWarning ? '💾 SAVE (đã xác nhận trùng)' : '💾 SAVE') }}
        </button>
        <button @click="clearScanForm" class="btn btn-secondary">CLEAR</button>
        <button
          @click="confirm2Ok = !confirm2Ok"
          class="btn"
          :class="confirm2Ok ? 'btn-success' : 'btn-secondary'"
          :disabled="scanIncomplete"
        >
          {{ confirm2Ok ? '✅ ĐÃ PHÊ DUYỆT' : 'PHÊ DUYỆT' }}
        </button>
        <button @click="checkDuplicateOrder" class="btn btn-secondary" :disabled="!scanForm.color || !scanForm.code">
          CHECK
        </button>
      </div>
      <p class="text-muted scan-footnote">
        Tick PHÊ DUYỆT trước khi SAVE = lưu &amp; gửi hàng chờ in tem ngay trong cùng thao tác (như VBA).
        Không tick = chỉ lưu đơn chờ (NEW), duyệt sau trong bảng bên dưới. Chọn Thùng là tùy chọn, không bắt buộc để duyệt.
      </p>
    </section>

    <!-- KPI Cards Grid -->
    <div class="kpi-grid mb-4">
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-title">Tổng lô hôm nay</span>
          <span class="kpi-icon">📊</span>
        </div>
        <div class="kpi-value">{{ kpis.total }}</div>
        <div class="kpi-subtext">Cập nhật thời gian thực</div>
      </div>
      <div class="kpi-card kpi-blue">
        <div class="kpi-header">
          <span class="kpi-title">Đang chuẩn bị</span>
          <span class="kpi-icon">⚙️</span>
        </div>
        <div class="kpi-value">{{ kpis.preparing }}</div>
        <div class="kpi-subtext">Mới tạo &amp; Chờ cân</div>
      </div>
      <div class="kpi-card kpi-yellow">
        <div class="kpi-header">
          <span class="kpi-title">Đang cân đo</span>
          <span class="kpi-icon">⚖️</span>
        </div>
        <div class="kpi-value">{{ kpis.weighing }}</div>
        <div class="kpi-subtext">Đang cân tại trạm cân</div>
      </div>
      <div class="kpi-card kpi-green">
        <div class="kpi-header">
          <span class="kpi-title">Đã cân (Chờ cấp máy)</span>
          <span class="kpi-icon">✅</span>
        </div>
        <div class="kpi-value">{{ kpis.weighed }}</div>
        <div class="kpi-subtext">Sẵn sàng nạp van</div>
      </div>
      <div class="kpi-card kpi-purple">
        <div class="kpi-header">
          <span class="kpi-title">Hoàn thành / Đã cấp</span>
          <span class="kpi-icon">⚡</span>
        </div>
        <div class="kpi-value">{{ kpis.completed }}</div>
        <div class="kpi-subtext">Đã cấp máy &amp; Đóng mẻ</div>
      </div>
    </div>

    <!-- Filter Bar -->
    <section class="section filter-section mb-4">
      <div class="filter-bar">
        <div class="filter-group flex-2">
          <label>Tìm kiếm lô sản xuất:</label>
          <div class="search-input-wrapper">
            <SvgIcon name="search" size="16" class="search-icon" />
            <input 
              v-model="searchQuery" 
              @input="onFilterChange"
              type="text" 
              placeholder="Nhập mã lô, mã màu, mã hàng..." 
              class="form-control pad-left-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label>Trạng thái:</label>
          <select v-model="statusFilter" @change="onFilterChange" class="form-select">
            <option value="">Tất cả trạng thái</option>
            <option value="NEW">Mới tạo (NEW)</option>
            <option value="APPROVED">Đã duyệt, chờ in tem (APPROVED)</option>
            <option value="READY_TO_WEIGH">Chờ cân (READY_TO_WEIGH)</option>
            <option value="WEIGHING">Đang cân (WEIGHING)</option>
            <option value="WEIGHED">Đã cân xong (WEIGHED)</option>
            <option value="SENT">Đã gửi máy (SENT)</option>
            <option value="DONE">Hoàn thành (DONE)</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Máy chỉ định:</label>
          <select v-model="machineFilter" @change="onFilterChange" class="form-select">
            <option value="">Tất cả máy nhuộm</option>
            <option v-for="m in machines" :key="m.id" :value="m.id">
              {{ m.code }} ({{ m.name }})
            </option>
          </select>
        </div>

        <div class="filter-group actions-group">
          <label>&nbsp;</label>
          <button @click="clearFilters" class="btn btn-secondary w-full-mobile">
            Xóa bộ lọc
          </button>
        </div>
      </div>
    </section>

    <!-- Main Data Table Card -->
    <section class="section card-sec">
      <div class="table-container-fixed">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mã Lô (Batch ID)</th>
              <th>Mã Màu (Color)</th>
              <th>Mã Hàng (Product)</th>
              <th>Máy Nhuộm</th>
              <th>Thùng Trộn</th>
              <th>Tiến Trình</th>
              <th>Trạng Thái</th>
              <th>Ngày Cập Nhật</th>
              <th>Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton Loading state -->
            <tr v-if="loading" v-for="i in 5" :key="'skel-' + i">
              <td v-for="j in 9" :key="'cell-' + j">
                <div class="skeleton" style="height: 20px; width: 80%;"></div>
              </td>
            </tr>

            <!-- Actual Table Rows -->
            <tr 
              v-else 
              v-for="batch in batches" 
              :key="batch.id" 
              @click="openDetailDrawer(batch)"
              class="clickable-row"
            >
              <td class="bold-text highlight-code">{{ batch.legacy_batch_id }}</td>
              <td>{{ batch.color }}</td>
              <td>{{ batch.product_code }}</td>
              <td>
                <span class="machine-tag">{{ batch.machine?.code || 'N/A' }}</span>
              </td>
              <td>
                <span class="tank-tag" v-if="batch.tank">{{ batch.tank?.code }}</span>
                <span class="text-muted" v-else>-</span>
              </td>
              <td>
                <div class="progress-bar-wrapper" :title="'Độ hoàn tất: ' + getProgressPercent(batch.status) + '%'">
                  <div class="progress-bar-fill" :style="{ width: getProgressPercent(batch.status) + '%' }"></div>
                </div>
              </td>
              <td>
                <span :class="['badge', getStatusBadgeClass(batch.status)]">
                  {{ batch.status }}
                </span>
              </td>
              <td class="date-cell">{{ formatDate(batch.updated_at || batch.created_at) }}</td>
              <td @click.stop class="actions-cell">
                <button
                  @click="openDetailDrawer(batch)"
                  class="btn btn-secondary btn-sm"
                  title="Xem chi tiết Thuốc nhuộm (DYE) và Hóa chất (CHEM) của lô này"
                >
                  👁️ DYE/CHEM
                </button>
                <button
                  v-if="batch.status === 'NEW'"
                  @click="approveBatch(batch)"
                  class="btn btn-primary btn-sm"
                  :disabled="approvingId === batch.id"
                >
                  {{ approvingId === batch.id ? 'Đang duyệt...' : '✅ Duyệt đơn' }}
                </button>
                <select
                  :value="batch.status"
                  @change="updateBatchStatus(batch.id, ($event.target as HTMLSelectElement).value)"
                  class="form-select table-row-select"
                >
                  <option value="NEW">NEW</option>
                  <option value="APPROVED">APPROVED</option>
                  <option value="READY_TO_WEIGH">READY_TO_WEIGH</option>
                  <option value="WEIGHING">WEIGHING</option>
                  <option value="WEIGHED">WEIGHED</option>
                  <option value="SENT">SENT</option>
                  <option value="DONE">DONE</option>
                </select>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!loading && batches.length === 0">
              <td colspan="9" class="text-center text-muted pad-empty-row">
                <div class="empty-state-icon">🔍</div>
                <p>Không tìm thấy lô sản xuất nào khớp với điều kiện lọc.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-footer" v-if="totalPages > 1 && !loading">
        <button 
          @click="changePage(currentPage - 1)" 
          :disabled="currentPage === 1" 
          class="btn btn-secondary btn-sm"
        >
          ◀ Trước
        </button>
        <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
        <button 
          @click="changePage(currentPage + 1)" 
          :disabled="currentPage === totalPages" 
          class="btn btn-secondary btn-sm"
        >
          Sau ▶
        </button>
      </div>
    </section>

    <!-- Create Mock Batch Drawer (Sliding Panel from Right) -->
    <div class="drawer-overlay" v-if="createDrawerOpen" @click.self="closeCreateDrawer">
      <div class="right-drawer">
        <div class="drawer-header">
          <div class="drawer-header-title">
            <h3>🚀 Giả lập phát hành Lô sản xuất</h3>
            <span class="sim-badge">Công cụ mô phỏng MES</span>
          </div>
          <button @click="closeCreateDrawer" class="drawer-close-btn">&times;</button>
        </div>

        <div class="drawer-body">
          <p class="drawer-desc mb-4">Nhập thông tin giả lập đẩy đơn lệnh nhuộm từ MES xuống trạm điều phối nhà máy.</p>

          <form @submit.prevent="createMockBatch" class="drawer-form">
            <div class="form-group">
              <label for="mock-batch-id">Mã Lô sản xuất (Batch ID) <span class="required">*</span></label>
              <input 
                id="mock-batch-id"
                v-model="mockForm.legacy_batch_id" 
                type="text" 
                required
                placeholder="Ví dụ: L2-260715-08"
                class="form-control"
              />
            </div>
            
            <div class="form-group">
              <label for="mock-color">Mã công thức màu (Color Code) <span class="required">*</span></label>
              <input 
                id="mock-color"
                v-model="mockForm.color" 
                type="text" 
                required
                placeholder="Ví dụ: A+110293"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="mock-product">Mã sản phẩm (Product Code) <span class="required">*</span></label>
              <input 
                id="mock-product"
                v-model="mockForm.product_code" 
                type="text" 
                required
                placeholder="Ví dụ: T7400"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="mock-machine">Máy nhuộm chỉ định <span class="required">*</span></label>
              <select id="mock-machine" v-model="mockForm.machine_id" required class="form-select">
                <option value="">-- Chọn máy nhuộm --</option>
                <option v-for="m in machines" :key="m.id" :value="m.id">
                  {{ m.code }} ({{ m.name }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="mock-tank">Thùng trộn nhuộm (Tùy chọn)</label>
              <select id="mock-tank" v-model="mockForm.tank_id" class="form-select">
                <option value="">-- Mặc định --</option>
                <option v-for="t in tanks" :key="t.id" :value="t.id">
                  Thùng {{ t.code }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="mock-level">Mã mức nước (Water Level Code)</label>
              <input 
                id="mock-level"
                v-model="mockForm.level_code" 
                type="text" 
                placeholder="Ví dụ: L-4 (Trung bình)"
                class="form-control"
              />
            </div>

            <!-- Error/Success feedbacks -->
            <p v-if="successMsg" class="text-success mt-2">✅ {{ successMsg }}</p>
            <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>

            <div class="drawer-actions mt-4">
              <button type="button" @click="closeCreateDrawer" class="btn btn-secondary flex-1">Hủy</button>
              <button type="submit" class="btn btn-primary flex-2" :disabled="creatingBatch">
                {{ creatingBatch ? 'Đang tạo...' : 'Phát hành Lệnh' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Batch Detail Drawer (Sliding Panel from Right) -->
    <div class="drawer-overlay" v-if="detailDrawerOpen" @click.self="closeDetailDrawer">
      <div class="right-drawer">
        <div class="drawer-header">
          <h3>🔍 Chi tiết Lô sản xuất</h3>
          <button @click="closeDetailDrawer" class="drawer-close-btn">&times;</button>
        </div>

        <div class="drawer-body" v-if="selectedBatch">
          <div class="detail-hero mb-4">
            <span :class="['badge', getStatusBadgeClass(selectedBatch.status)]" class="hero-badge">
              {{ selectedBatch.status }}
            </span>
            <h4>Lô: {{ selectedBatch.legacy_batch_id }}</h4>
          </div>

          <div class="detail-info-list">
            <div class="detail-item">
              <span class="detail-label">Mã màu công thức (Color)</span>
              <span class="detail-value">{{ selectedBatch.color }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Mã hàng sản phẩm (Product)</span>
              <span class="detail-value">{{ selectedBatch.product_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Máy nhuộm chỉ định</span>
              <span class="detail-value">{{ selectedBatch.machine ? selectedBatch.machine.code + ' - ' + selectedBatch.machine.name : 'N/A' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Thùng trộn nhuộm</span>
              <span class="detail-value">{{ selectedBatch.tank ? 'Thùng ' + selectedBatch.tank.code : 'N/A' }}</span>
            </div>
            <div class="detail-item" v-if="selectedBatch.level_code">
              <span class="detail-label">Mức nước chỉ định</span>
              <span class="detail-value">{{ selectedBatch.level_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Ngày khởi tạo</span>
              <span class="detail-value">{{ formatDate(selectedBatch.created_at) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Ngày cập nhật gần nhất</span>
              <span class="detail-value">{{ formatDate(selectedBatch.updated_at) }}</span>
            </div>
          </div>

          <!-- Bảng tách dòng RACK/MÃ/KHỐI LƯỢNG — giống layout scaleform.frm (VBA gốc) -->
          <div class="rack-tables-row mt-4" v-if="selectedDyeLines.length || selectedChemLines.length">
            <div class="rack-table-col">
              <label class="rack-table-title">🧵 Thuốc nhuộm (DYE)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ THUỐC NHUỘM</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedDyeLines" :key="'sel-dye-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="rack-table-col">
              <label class="rack-table-title">🧪 Hóa chất (CHEM)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ HÓA CHẤT</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedChemLines" :key="'sel-chem-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-else class="text-muted font-sm mt-4">Lô này không có dữ liệu quét DYE/CHEM (được tạo bằng tay hoặc từ MES giả lập).</p>

          <p v-if="approveErrorMsg" class="text-error mt-2">❌ {{ approveErrorMsg }}</p>

          <div class="drawer-actions mt-4">
            <button
              v-if="selectedBatch.status === 'NEW'"
              @click="approveBatch(selectedBatch)"
              class="btn btn-primary flex-2"
              :disabled="approvingId === selectedBatch.id"
            >
              {{ approvingId === selectedBatch.id ? 'Đang duyệt...' : '✅ Duyệt đơn → Tạo hàng chờ in tem' }}
            </button>
            <button @click="closeDetailDrawer" class="btn btn-secondary flex-1">Đóng cửa sổ</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, reactive, nextTick } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';
import SvgIcon from '../components/SvgIcon.vue';
import { currentWorkstation } from '../services/workstation';
import { parseRackLines } from '../utils/rackParser';

const authStore = useAuthStore();

// Table state
const batches = ref<any[]>([]);
const loading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);

// Filters state
const searchQuery = ref('');
const statusFilter = ref('');
const machineFilter = ref('');

// Metadata lists
const machines = ref<any[]>([]);
const tanks = ref<any[]>([]);

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

const resolveMachineIdFromCode = (code: string): number | null => {
  if (!code) return null;
  const upper = code.toUpperCase().trim();
  let match = machines.value.find(m => m.code.toUpperCase() === upper);
  if (!match) {
    const normalized = normalizeVdCode(upper);
    match = machines.value.find(m => m.code.toUpperCase() === normalized);
  }
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
  } catch (error: any) {
    scanErrorMsg.value = error.response?.data?.message || 'Không đọc được mã quét.';
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

// CHECK (btnCheck_Click trong checkform.frm) — kiểm tra trùng màu+mã hàng TRƯỚC khi Save,
// dùng chung logic chặn trùng với store() (chỉ tính đơn đang NEW).
const checkDuplicateOrder = async () => {
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';
  const duplicate = batches.value.some(
    b => b.status === 'NEW' && b.color === scanForm.color && b.product_code === scanForm.code
  );
  if (duplicate) {
    scanSuccessMsg.value = 'YES — Đơn đã tồn tại (đang chờ duyệt).';
  } else {
    scanSuccessMsg.value = 'NO — Chưa tồn tại, có thể lưu mới.';
  }
};

// SAVE (btnSAVE_Click): tạo đơn; nếu đã chọn Thùng + đã PHÊ DUYỆT -> duyệt ngay trong
// cùng thao tác (đúng VBA: "If confirm2=OK And tank<>"" Then MoveToSend").
const saveScanOrder = async () => {
  scanErrorMsg.value = '';
  scanSuccessMsg.value = '';

  if (!scanForm.color || !scanForm.code || !scanMachineId.value) {
    scanErrorMsg.value = 'Khong du thong tin (thiếu màu/mã hàng/máy).';
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

    // Tick PHÊ DUYỆT là đủ để tự động duyệt ngay trong cùng thao tác SAVE — không còn
    // bắt buộc phải chọn Thùng nữa (backend/ApproveProductionOrderService không yêu cầu
    // tank_id, quy tắc 250L chỉ áp dụng KHI có tank 1A/2B, tự bỏ qua nếu tank rỗng).
    if (confirm2Ok.value) {
      try {
        await axios.post(`/api/production-batches/${newBatch.id}/approve`, {
          workstation_id: currentWorkstation.value?.code
        });
        scanSuccessMsg.value = `Đã lưu và gửi hàng chờ in tem: ${scanForm.color} - ${scanForm.code}.`;
      } catch (approveErr: any) {
        // Lưu đơn đã thành công — chỉ riêng bước duyệt tự động thất bại (vd vi phạm quy
        // tắc 250L). Báo rõ để người vận hành tự duyệt lại thủ công trong bảng bên dưới,
        // không lặng lẽ nuốt lỗi.
        scanErrorMsg.value = `Đã lưu đơn nhưng DUYỆT TỰ ĐỘNG thất bại: ${approveErr.response?.data?.message || 'Lỗi không xác định'}. Duyệt lại thủ công ở bảng bên dưới.`;
      }
    } else {
      scanSuccessMsg.value = `Đã lưu đơn (chờ duyệt): ${scanForm.color} - ${scanForm.code}.`;
    }

    clearScanForm();
    fetchBatches();
  } catch (error: any) {
    if (error.response?.data?.status === 'DUPLICATE_WARNING') {
      duplicateWarning.value = error.response.data.message;
    } else {
      scanErrorMsg.value = error.response?.data?.message || 'Có lỗi khi lưu đơn.';
    }
  } finally {
    savingScan.value = false;
  }
};

// Drawers state
const createDrawerOpen = ref(false);
const detailDrawerOpen = ref(false);
const selectedBatch = ref<any | null>(null);
const selectedDyeLines = computed(() => parseRackLines(selectedBatch.value?.raw_qr_dye));
const selectedChemLines = computed(() => parseRackLines(selectedBatch.value?.raw_qr_chemical));

// Mock Form state
const mockForm = reactive({
  legacy_batch_id: '',
  color: '',
  product_code: '',
  machine_id: '',
  tank_id: '',
  level_code: ''
});
const creatingBatch = ref(false);
const successMsg = ref('');
const errorMsg = ref('');
const approvingId = ref<string | null>(null);
const approveErrorMsg = ref('');

// KPI stats
const kpis = computed(() => {
  const list = batches.value;
  return {
    total: list.length,
    preparing: list.filter(b => b.status === 'NEW' || b.status === 'APPROVED' || b.status === 'READY_TO_WEIGH').length,
    weighing: list.filter(b => b.status === 'WEIGHING').length,
    weighed: list.filter(b => b.status === 'WEIGHED').length,
    completed: list.filter(b => b.status === 'SENT' || b.status === 'DONE').length
  };
});

onMounted(() => {
  fetchBatches();
  fetchMetadata();
  scanInputRef.value?.focus();
});

const fetchBatches = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/production-batches', {
      params: {
        page: currentPage.value,
        status: statusFilter.value || undefined,
        search: searchQuery.value || undefined,
        machine_id: machineFilter.value || undefined
      }
    });
    batches.value = response.data.data;
    totalPages.value = response.data.last_page;
  } catch (error) {
    console.error('Error fetching batches:', error);
  } finally {
    loading.value = false;
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

const onFilterChange = () => {
  currentPage.value = 1;
  fetchBatches();
};

const clearFilters = () => {
  searchQuery.value = '';
  statusFilter.value = '';
  machineFilter.value = '';
  onFilterChange();
};

const changePage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchBatches();
  }
};

const updateBatchStatus = async (id: string, newStatus: string) => {
  try {
    await axios.put(`/api/production-batches/${id}/status`, {
      status: newStatus
    });
    fetchBatches();
  } catch (error) {
    console.error('Error updating batch status:', error);
    alert('Không thể cập nhật trạng thái lô.');
  }
};

// Duyệt đơn -> tạo hàng chờ điều phối cho QR_LABEL_PRINTING (ApproveProductionOrderService).
// Khác updateBatchStatus (đổi status tự do, không quy tắc gì): endpoint này có transaction,
// idempotent, và áp quy tắc 250L (VD006-VD013 + tank 1A/2B + level<250 -> chặn).
const approveBatch = async (batch: any) => {
  approveErrorMsg.value = '';
  approvingId.value = batch.id;
  try {
    await axios.post(`/api/production-batches/${batch.id}/approve`, {
      workstation_id: currentWorkstation.value?.code
    });
    batch.status = 'APPROVED';
    if (selectedBatch.value && selectedBatch.value.id === batch.id) {
      selectedBatch.value.status = 'APPROVED';
    }
    fetchBatches();
  } catch (error: any) {
    const msg = error.response?.data?.message || 'Không thể duyệt đơn.';
    approveErrorMsg.value = msg;
    alert(msg);
  } finally {
    approvingId.value = null;
  }
};

const createMockBatch = async () => {
  successMsg.value = '';
  errorMsg.value = '';
  
  if (!mockForm.legacy_batch_id || !mockForm.color || !mockForm.product_code || !mockForm.machine_id) {
    errorMsg.value = 'Vui lòng điền đầy đủ các thông tin bắt buộc!';
    return;
  }

  creatingBatch.value = true;
  try {
    await axios.post('/api/production-batches', {
      legacy_batch_id: mockForm.legacy_batch_id,
      color: mockForm.color,
      product_code: mockForm.product_code,
      machine_id: mockForm.machine_id,
      tank_id: mockForm.tank_id || null,
      level_code: mockForm.level_code || null,
      status: 'NEW'
    });

    successMsg.value = 'Đẩy đơn sản xuất từ MES thành công! Trạng thái: NEW — vào chi tiết lô để bấm "Duyệt đơn".';
    // Reset form fields
    mockForm.legacy_batch_id = '';
    mockForm.color = '';
    mockForm.product_code = '';
    mockForm.machine_id = '';
    mockForm.tank_id = '';
    mockForm.level_code = '';
    
    fetchBatches();
    setTimeout(() => {
      closeCreateDrawer();
    }, 1500);
  } catch (error: any) {
    errorMsg.value = error.response?.data?.message || 'Có lỗi xảy ra khi kết nối MES.';
  } finally {
    creatingBatch.value = false;
  }
};

// Drawer controls
const openCreateDrawer = () => {
  successMsg.value = '';
  errorMsg.value = '';
  createDrawerOpen.value = true;
};
const closeCreateDrawer = () => {
  createDrawerOpen.value = false;
};

const openDetailDrawer = (batch: any) => {
  selectedBatch.value = batch;
  detailDrawerOpen.value = true;
};
const closeDetailDrawer = () => {
  detailDrawerOpen.value = false;
  selectedBatch.value = null;
};

// Progress percent calculation
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

const getStatusBadgeClass = (status: string) => {
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

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN');
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

/* KPI cards styling */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-lg);
}

.kpi-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-lg) var(--space-xl);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background-color: var(--status-grey);
}

.kpi-blue::before { background-color: var(--status-blue); }
.kpi-yellow::before { background-color: var(--status-yellow); }
.kpi-green::before { background-color: var(--status-green); }
.kpi-purple::before { background-color: var(--status-purple); }

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kpi-icon {
  font-size: 1.2rem;
}

.kpi-value {
  font-family: 'Outfit', sans-serif;
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-title);
  line-height: 1.2;
}

.kpi-subtext {
  font-size: 11px;
  color: var(--text-disabled);
}

/* Filter bar styles */
.filter-bar {
  display: flex;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 150px;
}

.filter-group label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: var(--space-lg);
  color: var(--text-disabled);
}

.pad-left-search {
  padding-left: calc(var(--space-lg) * 2 + 10px);
}

/* Data Table and custom columns styling */
.table-container-fixed {
  width: 100%;
  overflow-x: auto;
  border-radius: var(--radius-lg);
}

.clickable-row {
  cursor: pointer;
}

.bold-text {
  font-weight: 700;
}

.highlight-code {
  color: var(--primary-hover);
  font-family: monospace;
}

.machine-tag, .tank-tag {
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card);
  padding: 3px var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
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

.table-row-select {
  height: 32px;
  padding: 0 var(--space-sm);
  font-size: 12px;
  width: auto;
}

.pad-empty-row {
  padding: var(--space-4xl) 0 !important;
}

.empty-state-icon {
  font-size: 3rem;
  margin-bottom: var(--space-sm);
  opacity: 0.5;
}

.pagination-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: var(--space-lg);
  margin-top: var(--space-2xl);
  padding-top: var(--space-lg);
  border-top: 1px solid var(--border-divider);
}

.page-info {
  font-size: 13px;
  color: var(--text-muted);
}

/* Right Drawer Panel (MES Mock tool and Details) */
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

.drawer-header {
  height: 70px;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
}

.drawer-header-title {
  display: flex;
  flex-direction: column;
}

.sim-badge {
  font-size: 9px;
  background-color: var(--status-red-bg);
  border: 1px solid var(--status-red-border);
  color: var(--status-red);
  font-weight: 700;
  padding: 1px 4px;
  border-radius: var(--radius-sm);
  width: fit-content;
  text-transform: uppercase;
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
}

.drawer-desc {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.drawer-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 600;
  color: var(--text-title);
  font-size: 0.9rem;
}

.required {
  color: var(--status-red);
}

.drawer-actions {
  display: flex;
  gap: var(--space-lg);
}

/* Detail Hero Area */
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
  
  .filter-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .w-full-mobile {
    width: 100% !important;
  }
}
</style>
