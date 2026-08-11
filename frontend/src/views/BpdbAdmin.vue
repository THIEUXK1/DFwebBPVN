<template>
  <div class="bpdb-admin-container">
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">ADMIN — BPDB / JIT</span>
        <h2>{{ $t('bpdbAdmin.pageTitle') }}</h2>
        <p class="text-muted font-sm">{{ $t('bpdbAdmin.pageSubtitle') }}</p>
      </div>
      <div class="banner-right status-board font-sm">
        <div class="status-indicator">
          {{ $t('bpdbAdmin.bpdbConnectionLabel') }}
          <strong :class="bpdbConnected ? 'text-success' : 'text-error'">
            {{ bpdbConnected ? $t('bpdbAdmin.connOnline') : $t('bpdbAdmin.connOffline') }}
          </strong>
        </div>
        <button class="btn btn-secondary btn-sm" @click="fetchActiveTab" :disabled="loading">
          {{ loading ? $t('common.loading') : $t('bpdbAdmin.refreshBtn') }}
        </button>
      </div>
    </div>

    <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>

    <div v-if="!bpdbConnected" class="stale-banner error-banner mt-2">
      {{ $t('bpdbAdmin.disconnectedBanner', { time: formatTime(lastSyncedAt) }) }}
    </div>
    <div v-else-if="dataStale" class="stale-banner mt-2">
      {{ $t('bpdbAdmin.staleBanner', { time: formatTime(lastSyncedAt), age: dataAgeSeconds }) }}
    </div>

    <!-- Tabs -->
    <div class="tab-bar mt-3">
      <button v-for="tab in tabs" :key="tab.key" class="tab-btn" :class="{ active: activeTab === tab.key }" @click="switchTab(tab.key)">
        {{ $t(tab.labelKey) }}
      </button>
    </div>

    <!-- TAB: Tổng quan -->
    <div v-if="activeTab === 'overview'">
      <div class="summary-grid" v-if="overview">
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.summaryTotalLinks') }}</div>
          <div class="summary-value">{{ overview.summary.total_links }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.summaryCompletionRate') }}</div>
          <div class="summary-value text-success">{{ overview.summary.completion_rate ?? '—' }}%</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.summaryCancellationRate') }}</div>
          <div class="summary-value text-error">{{ overview.summary.cancellation_rate ?? '—' }}%</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.summaryAvgProcessing') }}</div>
          <div class="summary-value">{{ formatDuration(overview.summary.avg_processing_seconds) }}</div>
        </div>
      </div>

      <div class="section card-sec mt-3" v-if="overview">
        <h3>{{ $t('bpdbAdmin.adapterStatusDistTitle') }}</h3>
        <div class="status-dist">
          <span v-for="(cnt, key) in overview.summary.by_adapter_status" :key="key" class="status-chip">
            {{ key }}: <strong>{{ cnt }}</strong>
          </span>
          <span v-if="!Object.keys(overview.summary.by_adapter_status || {}).length" class="text-muted">{{ $t('bpdbAdmin.noDataYet') }}</span>
        </div>
      </div>

      <div class="section card-sec mt-3" v-if="overview?.not_available">
        <h3 class="text-muted">{{ $t('bpdbAdmin.noDataToShow') }}</h3>
        <ul class="font-xs text-muted">
          <li v-for="(msg, key) in overview.not_available" :key="key">{{ msg }}</li>
        </ul>
      </div>
    </div>

    <!-- TAB: Lệnh BPDB -->
    <div v-if="activeTab === 'taskLinks'">
      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>{{ $t('bpdbAdmin.recentLinksTitle') }}</h3>
          <select v-model="statusFilter" @change="fetchTaskLinks" class="form-select font-xs">
            <option value="">{{ $t('bpdbAdmin.allStatuses') }}</option>
            <option value="AWAITING_SCAN">AWAITING_SCAN</option>
            <option value="AMBIGUOUS">AMBIGUOUS</option>
            <option value="BPDB_ACCEPTED">BPDB_ACCEPTED</option>
            <option value="BPDB_ASSIGNED">BPDB_ASSIGNED</option>
            <option value="BPDB_PROCESSING">BPDB_PROCESSING</option>
            <option value="BPDB_COMPLETED">BPDB_COMPLETED</option>
            <option value="CANCELLED">CANCELLED</option>
          </select>
        </div>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colColorProduct') }}</th><th>{{ $t('bpdbAdmin.colMachineTank') }}</th><th>JIT</th><th>TaskTitle BPDB</th><th>Status BPDB</th><th>Adapter Status</th><th>{{ $t('bpdbAdmin.colLastSync') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in taskLinks" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td>{{ row.jit_queue_code || '—' }}</td>
              <td class="mono-text-sm">{{ row.bpdb_task_title || '—' }}</td>
              <td>{{ rawTaskStatusLabel(row.bpdb_task_status) }}</td>
              <td><span class="status-badge">{{ row.adapter_status }}</span></td>
              <td>{{ formatTime(row.last_synced_at) }}</td>
            </tr>
            <tr v-if="!taskLinks.length"><td colspan="7" class="text-muted text-center">{{ $t('common.noData') }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: Nhu cầu bơm hóa chất -->
    <div v-if="activeTab === 'chemicalDemand'">
      <div class="connection-disclaimer font-xs">
        {{ $t('bpdbAdmin.chemDemandDisclaimer') }}
      </div>

      <div class="summary-grid mt-2" v-if="demandSummary">
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardMachinesWaiting') }}</div><div class="summary-value" style="color:#ca8a04">{{ demandSummary.machinesWaiting }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardMachinesProcessing') }}</div><div class="summary-value" style="color:#2563eb">{{ demandSummary.machinesProcessing }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardTasksWaiting') }}</div><div class="summary-value">{{ demandSummary.tasksWaiting }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardTasksProcessing') }}</div><div class="summary-value">{{ demandSummary.tasksProcessing }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardCompletedToday') }}</div><div class="summary-value text-success">{{ demandSummary.completedToday }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardErrorToday') }}</div><div class="summary-value text-error">{{ demandSummary.errorOrCancelledToday }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardLongestWaiting') }}</div><div class="summary-value">{{ formatDuration(demandSummary.longestWaitingSeconds) }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardTopJitQueue') }}</div><div class="summary-value">{{ demandSummary.topJitQueue || '—' }}<span v-if="demandSummary.topJitQueue" class="font-xs text-muted"> ({{ demandSummary.topJitQueueLoad }})</span></div></div>
      </div>
      <p v-if="demandSummary?.stuckCount || demandSummary?.orphanedCount" class="text-error font-xs mt-1">
        {{ $t('bpdbAdmin.stuckOrphanWarning', { stuck: demandSummary.stuckCount, orphaned: demandSummary.orphanedCount, threshold: orphanThresholdLabel }) }}
      </p>
      <p class="text-muted font-xs mt-1">
        {{ $t('bpdbAdmin.lastUpdatedLabel') }} <strong>{{ formatTime(lastSyncedAt) }}</strong> · {{ $t('bpdbAdmin.sourceLabel') }} <strong>{{ $t('bpdbAdmin.sourceReadOnly') }}</strong>
        <span v-if="dataStale" class="text-error"> · {{ $t('bpdbAdmin.staleText') }}</span>
      </p>

      <div class="section card-sec mt-2">
        <h3>{{ $t('bpdbAdmin.activeSectionTitle', { count: demandActiveRecent.length }) }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>JIT</th><th>{{ $t('common.status') }}</th><th>Task</th><th>{{ $t('bpdbAdmin.colWaitProcessTime') }}</th><th>{{ $t('bpdbAdmin.colChemicalCount') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in demandActiveRecent" :key="t.taskId" class="demand-row" :class="'demand-' + t.displayStatus.toLowerCase()" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatDuration(t.waitingSeconds) }}</td>
              <td>{{ t.chemicalCount }}</td>
            </tr>
            <tr v-if="!demandActiveRecent.length"><td colspan="7" class="text-muted text-center">{{ $t('bpdbAdmin.noActiveNormalTasks') }}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3" v-if="demandStuck.length">
        <h3 class="text-error">{{ $t('bpdbAdmin.stuckSectionTitle', { count: demandStuck.length }) }}</h3>
        <p class="font-xs text-muted">{{ $t('bpdbAdmin.stuckDesc') }}</p>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>JIT</th><th>{{ $t('common.status') }}</th><th>Task</th><th>{{ $t('bpdbAdmin.colWaitProcessTime') }}</th><th>{{ $t('common.warning') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in demandStuck" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td class="text-error">{{ formatDuration(t.waitingSeconds) }}</td>
              <td class="font-xs text-error">{{ t.stuckWarning?.code }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3 class="text-muted">{{ $t('bpdbAdmin.orphanedSectionTitle', { count: demandOrphaned.length }) }}</h3>
          <label class="font-xs" style="display:flex;align-items:center;gap:0.3rem;cursor:pointer;">
            <input type="checkbox" v-model="hideOrphaned" /> {{ $t('bpdbAdmin.hideOrphanedLabel') }}
          </label>
        </div>
        <p class="font-xs text-muted">{{ $t('bpdbAdmin.orphanedDesc', { threshold: orphanThresholdLabel }) }}</p>
        <table class="data-table" v-if="!hideOrphaned && demandOrphaned.length">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>JIT</th><th>{{ $t('common.status') }}</th><th>Task</th><th>{{ $t('bpdbAdmin.colTaskAge') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in demandOrphaned" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatDuration(t.ageSeconds) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else-if="hideOrphaned && demandOrphaned.length" class="text-muted font-sm">{{ $t('bpdbAdmin.orphanedHiddenMsg', { count: demandOrphaned.length }) }}</p>
        <p v-else class="text-muted font-sm">{{ $t('bpdbAdmin.noOrphaned') }}</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.completedSectionTitle', { count: demandCompleted.length }) }}</h3>
        <p class="font-xs text-muted">{{ $t('bpdbAdmin.completedDescPrefix') }}<code>TaskStatus=40</code>{{ $t('bpdbAdmin.completedDescSuffix') }}</p>
        <table class="data-table" v-if="demandCompleted.length">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>JIT</th><th>Task</th><th>{{ $t('bpdbAdmin.colFinishedAt') }}</th><th>{{ $t('bpdbAdmin.colChemicalCount') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in demandCompleted" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatTime(t.finishTime) }}</td>
              <td>{{ t.chemicalCount }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">{{ $t('bpdbAdmin.noCompletedRecently') }}</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.errorsSectionTitle', { count: demandErrors.length }) }}</h3>
        <table class="data-table" v-if="demandErrors.length">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>{{ $t('common.status') }}</th><th>Task</th><th>{{ $t('common.error') }}</th><th>{{ $t('bpdbAdmin.colCreatedAt') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in demandErrors" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td class="font-xs text-error">{{ t.errorMessage || '—' }}</td>
              <td>{{ formatTime(t.createdAt) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">{{ $t('bpdbAdmin.noErrorTasks') }}</p>
      </div>
    </div>

    <!-- TAB: Thống kê hoạt động nguyên liệu -->
    <div v-if="activeTab === 'materialActivity'">
      <div class="connection-disclaimer font-xs">
        {{ $t('bpdbAdmin.maDisclaimerPrefix') }}<strong>{{ $t('bpdbAdmin.maDisclaimerStrong') }}</strong>{{ $t('bpdbAdmin.maDisclaimerMid') }}<code>SUP_TaskDetails.GramsDosed/DaDosare</code>{{ $t('bpdbAdmin.maDisclaimerSuffix') }}
      </div>

      <div class="filter-row mt-2" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <label class="font-xs">{{ $t('bpdbAdmin.fromDate') }} <input type="date" v-model="maFrom" class="form-select font-xs" /></label>
        <label class="font-xs">{{ $t('bpdbAdmin.toDate') }} <input type="date" v-model="maTo" class="form-select font-xs" /></label>
        <select v-model="maMachineFilter" class="form-select font-xs">
          <option value="">{{ $t('bpdbAdmin.allMachines') }}</option>
          <option v-for="m in maByMachine" :key="m.machineCode" :value="m.machineCode">{{ m.machineCode }}</option>
        </select>
        <button class="btn btn-secondary btn-sm" @click="fetchMaterialActivity" :disabled="maLoading">
          {{ maLoading ? $t('common.loading') : $t('bpdbAdmin.applyBtn') }}
        </button>
      </div>

      <div class="summary-grid mt-2" v-if="maSummary">
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardCompletedTasks') }}</div><div class="summary-value">{{ maSummary.completedTaskCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardActiveMachines') }}</div><div class="summary-value">{{ maSummary.machineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardMaterialCodes') }}</div><div class="summary-value">{{ maSummary.distinctMaterialCodeCount }}</div></div>
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.cardWeightCoverage') }}</div>
          <div class="summary-value" :class="maSummary.weightDataCoveragePercent < 5 ? 'text-error' : 'text-success'">{{ maSummary.weightDataCoveragePercent }}%</div>
        </div>
      </div>
      <p v-if="maSummary && maSummary.weightDataCoveragePercent < 5" class="text-error font-xs mt-1">
        {{ $t('bpdbAdmin.weightDataWarning', { available: maSummary.weightDataRowsAvailable, total: maSummary.weightDataRowsTotal }) }}
      </p>

      <div class="connection-disclaimer font-xs mt-2" v-if="maSummary?.supStorico">
        ℹ️ <strong>{{ $t('bpdbAdmin.supStoricoNoteStrong') }}</strong>{{ $t('bpdbAdmin.supStoricoNoteMid') }}<code>BPVN2025.SUP_Storico</code>{{ $t('bpdbAdmin.supStoricoNoteSuffix') }}
      </div>
      <div class="summary-grid mt-2" v-if="maSummary?.supStorico">
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardDosingLinesStorico') }}</div><div class="summary-value">{{ maSummary.supStorico.dosingLineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardRequestedGrams') }}</div><div class="summary-value">{{ maSummary.supStorico.sumRequestedGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardActualGrams') }}</div><div class="summary-value text-success">{{ maSummary.supStorico.sumActualGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card">
          <div class="summary-label">{{ $t('bpdbAdmin.cardDeviationGrams') }}</div>
          <div class="summary-value" :class="maSummary.supStorico.sumDeviationGrams < 0 ? 'text-error' : ''">{{ maSummary.supStorico.sumDeviationGrams.toLocaleString('vi-VN') }}</div>
        </div>
      </div>
      <p v-if="maSummary?.unmappedMachineTaskWarning" class="text-error font-xs mt-1">
        {{ $t('bpdbAdmin.unmappedMachineWarning', { machineCount: maSummary.machineCount, mappedCount: maSummary.mappedMachineCount }) }}
      </p>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.byMachineTitle', { count: maByMachine.length }) }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>{{ $t('bpdbAdmin.cardCompletedTasks') }}</th><th>{{ $t('bpdbAdmin.colMaterialGroupCount') }}</th><th>{{ $t('bpdbAdmin.colLastActivity') }}</th><th>{{ $t('bpdbAdmin.colActualGramsStorico') }}</th></tr></thead>
          <tbody>
            <tr v-for="m in maByMachine" :key="m.machineCode">
              <td>{{ m.machineCode }}</td>
              <td>
                <div class="ma-bar-cell">
                  <div class="ma-bar" :style="{ width: maBarWidth(m.completedTaskCount, maMaxMachineCount) }"></div>
                  <span>{{ m.completedTaskCount }}</span>
                </div>
              </td>
              <td>{{ m.distinctMaterialCodeCount }}</td>
              <td>{{ formatTime(m.lastFinishTime) }}</td>
              <td>{{ m.supStorico ? m.supStorico.sumActualGrams.toLocaleString('vi-VN') : '—' }}</td>
            </tr>
            <tr v-if="!maByMachine.length"><td colspan="5" class="text-muted text-center">{{ $t('bpdbAdmin.noDataInRange') }}</td></tr>
          </tbody>
        </table>
        <p v-if="maByMachineData?.unmappedTaskCount" class="font-xs text-muted mt-1">{{ $t('bpdbAdmin.unmappedTaskNote', { count: maByMachineData.unmappedTaskCount }) }}</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.byMaterialTitle', { count: maByMaterial.length }) }}</h3>
        <p class="font-xs text-muted">{{ $t('bpdbAdmin.byMaterialDesc') }}</p>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colCodeGroup') }}</th><th>{{ $t('bpdbAdmin.colSampleName') }}</th><th>{{ $t('bpdbAdmin.cardCompletedTasks') }}</th><th>{{ $t('bpdbAdmin.colMachineUsedCount') }}</th></tr></thead>
          <tbody>
            <tr v-for="mt in maByMaterial" :key="mt.materialCode">
              <td>{{ mt.materialCode }}</td>
              <td>
                {{ mt.sampleName }}
                <span v-if="mt.multipleNamesWarning" class="text-error font-xs" :title="$t('bpdbAdmin.multipleNamesTitle')"> {{ $t('bpdbAdmin.multipleNamesBadge') }}</span>
              </td>
              <td>{{ mt.completedTaskCount }}</td>
              <td>{{ mt.machineCount }}</td>
            </tr>
            <tr v-if="!maByMaterial.length"><td colspan="4" class="text-muted text-center">{{ $t('bpdbAdmin.noDataInRange') }}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3" v-if="maTimeseries.length">
        <h3>{{ $t('bpdbAdmin.byDayTitle') }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('common.date') }}</th><th>{{ $t('bpdbAdmin.cardCompletedTasks') }}</th></tr></thead>
          <tbody>
            <tr v-for="p in maTimeseries" :key="p.periodStart">
              <td>{{ p.periodStart }}</td>
              <td>
                <div class="ma-bar-cell">
                  <div class="ma-bar" :style="{ width: maBarWidth(p.completedTaskCount, maMaxDayCount) }"></div>
                  <span>{{ p.completedTaskCount }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>{{ $t('bpdbAdmin.taskDetailTitle', { count: maDetailTotal }) }}</h3>
          <a class="btn btn-secondary btn-sm" :href="maExportUrl" target="_blank" rel="noopener">{{ $t('bpdbAdmin.exportExcelBtn') }}</a>
        </div>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Task</th><th>{{ $t('bpdbAdmin.colFinish') }}</th><th>{{ $t('bpdbAdmin.colMaterial') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in maDetail" :key="t.taskId">
              <td>{{ t.machineCode || '—' }}</td>
              <td class="mono-text-sm">{{ t.taskTitle }}</td>
              <td>{{ formatTime(t.finishTime) }}</td>
              <td class="font-xs">
                <span v-for="(l, i) in t.materialLines" :key="i">{{ l.materialCode }}<span v-if="i < t.materialLines.length - 1">, </span></span>
              </td>
            </tr>
            <tr v-if="!maDetail.length"><td colspan="4" class="text-muted text-center">{{ $t('common.noData') }}</td></tr>
          </tbody>
        </table>
        <div class="flex-header mt-1" v-if="maDetailTotal > maPerPage">
          <button class="btn btn-secondary btn-sm" :disabled="maPage <= 1" @click="changeMaPage(maPage - 1)">{{ $t('bpdbAdmin.prevPage') }}</button>
          <span class="font-xs">{{ $t('bpdbAdmin.pageOf', { page: maPage, total: Math.max(1, Math.ceil(maDetailTotal / maPerPage)) }) }}</span>
          <button class="btn btn-secondary btn-sm" :disabled="maPage * maPerPage >= maDetailTotal" @click="changeMaPage(maPage + 1)">{{ $t('bpdbAdmin.nextPage') }}</button>
        </div>
      </div>
    </div>

    <!-- TAB: Cấp máy (Mức A — BPVN2025.SUP_Storico) -->
    <div v-if="activeTab === 'machineFeeding'">
      <div class="connection-disclaimer font-xs">
        ℹ️ <strong>{{ $t('bpdbAdmin.mfDisclaimerStrong') }}</strong>{{ $t('bpdbAdmin.mfDisclaimerMid') }}<code>BPVN2025.SUP_Storico</code>{{ $t('bpdbAdmin.mfDisclaimerSuffix') }}
      </div>

      <div class="filter-row mt-2" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <label class="font-xs">{{ $t('bpdbAdmin.fromDate') }} <input type="date" v-model="mfFrom" class="form-select font-xs" /></label>
        <label class="font-xs">{{ $t('bpdbAdmin.toDate') }} <input type="date" v-model="mfTo" class="form-select font-xs" /></label>
        <select v-model="mfMachineFilter" class="form-select font-xs">
          <option value="">{{ $t('bpdbAdmin.allMachines') }}</option>
          <option v-for="m in mfByMachine" :key="m.machineCode" :value="m.machineCode">{{ m.machineCode }}</option>
        </select>
        <button class="btn btn-secondary btn-sm" @click="fetchMachineFeeding" :disabled="mfLoading">
          {{ mfLoading ? $t('common.loading') : $t('bpdbAdmin.applyBtn') }}
        </button>
      </div>

      <div class="summary-grid mt-2" v-if="mfSummary">
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardDosingLines') }}</div><div class="summary-value">{{ mfSummary.dosingLineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardMachineCount') }}</div><div class="summary-value">{{ mfSummary.machineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardLotCount') }}</div><div class="summary-value">{{ mfSummary.lotCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardProductCount') }}</div><div class="summary-value">{{ mfSummary.distinctProductCount }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardRequestedGrams') }}</div><div class="summary-value">{{ mfSummary.sumRequestedGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card"><div class="summary-label">{{ $t('bpdbAdmin.cardActualGrams') }}</div><div class="summary-value text-success">{{ mfSummary.sumActualGrams.toLocaleString('vi-VN') }}</div></div>
      </div>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.byMachineTitle', { count: mfByMachine.length }) }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>{{ $t('bpdbAdmin.cardDosingLines') }}</th><th>{{ $t('bpdbAdmin.cardLotCount') }}</th><th>{{ $t('bpdbAdmin.cardRequestedGrams') }}</th><th>{{ $t('bpdbAdmin.cardActualGrams') }}</th><th>{{ $t('bpdbAdmin.colLatest') }}</th></tr></thead>
          <tbody>
            <tr v-for="m in mfByMachine" :key="m.machineCode">
              <td>{{ m.machineCode }}</td>
              <td>{{ m.dosingLineCount }}</td>
              <td>{{ m.lotCount }}</td>
              <td>{{ m.sumRequestedGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ m.sumActualGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ formatTime(m.lastFinishTime) }}</td>
            </tr>
            <tr v-if="!mfByMachine.length"><td colspan="6" class="text-muted text-center">{{ $t('bpdbAdmin.noDataInRange') }}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.byProductTitle', { count: mfByMaterial.length }) }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colProductCode') }}</th><th>{{ $t('bpdbAdmin.cardDosingLines') }}</th><th>{{ $t('bpdbAdmin.colMachineUsedCount') }}</th><th>{{ $t('bpdbAdmin.cardRequestedGrams') }}</th><th>{{ $t('bpdbAdmin.cardActualGrams') }}</th></tr></thead>
          <tbody>
            <tr v-for="mt in mfByMaterial" :key="mt.materialCode">
              <td>{{ mt.materialCode }}</td>
              <td>{{ mt.dosingLineCount }}</td>
              <td>{{ mt.machineCount }}</td>
              <td>{{ mt.sumRequestedGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ mt.sumActualGrams.toLocaleString('vi-VN') }}</td>
            </tr>
            <tr v-if="!mfByMaterial.length"><td colspan="5" class="text-muted text-center">{{ $t('bpdbAdmin.noDataInRange') }}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>{{ $t('bpdbAdmin.dosingDetailTitle', { count: mfDetailTotal }) }}</h3>
          <a class="btn btn-secondary btn-sm" :href="mfExportUrl" target="_blank" rel="noopener">{{ $t('bpdbAdmin.exportExcelBtn') }}</a>
        </div>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>{{ $t('bpdbAdmin.colLot') }}</th><th>{{ $t('bpdbAdmin.colProductCodeShort') }}</th><th>{{ $t('bpdbAdmin.colRequestedG') }}</th><th>{{ $t('bpdbAdmin.colActualG') }}</th><th>{{ $t('bpdbAdmin.colStart') }}</th><th>{{ $t('bpdbAdmin.colFinish') }}</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in mfDetail" :key="i">
              <td>{{ r.machineCode }}</td>
              <td>{{ r.tankLetterCode || r.tank }}</td>
              <td class="mono-text-sm">{{ r.lot }}</td>
              <td>{{ r.materialCode }}</td>
              <td>{{ r.requestedGrams.toLocaleString('vi-VN') }}</td>
              <td :class="r.actualGrams < r.requestedGrams ? 'text-error' : ''">{{ r.actualGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ formatTime(r.startTime) }}</td>
              <td>{{ formatTime(r.finishTime) }}</td>
            </tr>
            <tr v-if="!mfDetail.length"><td colspan="8" class="text-muted text-center">{{ $t('common.noData') }}</td></tr>
          </tbody>
        </table>
        <div class="flex-header mt-1" v-if="mfDetailTotal > mfPerPage">
          <button class="btn btn-secondary btn-sm" :disabled="mfPage <= 1" @click="changeMfPage(mfPage - 1)">{{ $t('bpdbAdmin.prevPage') }}</button>
          <span class="font-xs">{{ $t('bpdbAdmin.pageOf', { page: mfPage, total: Math.max(1, Math.ceil(mfDetailTotal / mfPerPage)) }) }}</span>
          <button class="btn btn-secondary btn-sm" :disabled="mfPage * mfPerPage >= mfDetailTotal" @click="changeMfPage(mfPage + 1)">{{ $t('bpdbAdmin.nextPage') }}</button>
        </div>
      </div>
    </div>

    <!-- TAB: Theo dõi xử lý JIT ("Vận chuyển" — Mức B, tái dùng dữ liệu trạng thái máy) -->
    <div v-if="activeTab === 'transport'">
      <div class="connection-disclaimer font-xs">
        ℹ️ <strong>{{ $t('bpdbAdmin.transportDisclaimerStrong') }}</strong>{{ $t('bpdbAdmin.transportDisclaimerRest') }}
      </div>
      <p class="text-muted font-xs mt-1">{{ $t('bpdbAdmin.transportNotePrefix') }}<router-link to="/bpdb-machines">{{ $t('bpdbAdmin.transportNoteLink') }}</router-link>{{ $t('bpdbAdmin.transportNoteSuffix') }}</p>

      <table class="data-table mt-2">
        <thead><tr><th>Task</th><th>{{ $t('bpdbAdmin.colTargetMachine') }}</th><th>Tank</th><th>JIT queue</th><th>{{ $t('bpdbAdmin.colEvidenceStatus') }}</th><th>{{ $t('bpdbAdmin.colStart') }}</th><th>{{ $t('bpdbAdmin.colLastUpdate') }}</th></tr></thead>
        <tbody>
          <tr v-for="m in transportRows" :key="m.machineCode">
            <td class="mono-text-sm">{{ m.currentTask.taskTitle }}</td>
            <td>{{ m.machineCode }}</td>
            <td>{{ m.currentTask.tank || '—' }}</td>
            <td>{{ m.currentTask.jitQueue || '—' }}</td>
            <td><span class="op-status-badge" :class="'status-' + m.operationalStatus.toLowerCase()">{{ m.operationalStatus }}</span></td>
            <td>{{ formatTime(m.currentTask.workStartTime) }}</td>
            <td>{{ formatTime(m.lastActivityAt) }}</td>
          </tr>
          <tr v-if="!transportRows.length"><td colspan="7" class="text-muted text-center">{{ $t('bpdbAdmin.noActiveTask') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Định tuyến JIT -->
    <div v-if="activeTab === 'jit'">
      <div class="section card-sec mt-3">
        <h3>{{ $t('bpdbAdmin.jitRulesTitle') }}</h3>
        <table class="data-table">
          <thead><tr><th>{{ $t('bpdbAdmin.colMachine') }}</th><th>Tank</th><th>{{ $t('bpdbAdmin.colWaterLevel') }}</th><th>JIT Queue</th><th>B24 Route</th><th>QR Mode</th><th>{{ $t('bpdbAdmin.colPriority') }}</th></tr></thead>
          <tbody>
            <tr v-for="rule in jitRules.slice(0, 80)" :key="rule.id">
              <td>{{ rule.machine_code }}</td>
              <td>{{ rule.tank_code }}</td>
              <td>{{ rule.water_level ?? $t('bpdbAdmin.anyWaterLevel') }}</td>
              <td>{{ rule.jit_queue_code || '—' }}</td>
              <td class="font-xs">{{ rule.b24_route }}</td>
              <td>{{ rule.qr_mode }}</td>
              <td>{{ rule.priority }}</td>
            </tr>
          </tbody>
        </table>
        <p class="text-muted font-xs mt-1">{{ $t('bpdbAdmin.rulesShownNote', { shown: Math.min(80, jitRules.length), total: jitRules.length, source: jitRules[0]?.source_reference || 'VBA' }) }}</p>
      </div>
    </div>

    <!-- TAB: Lỗi đồng bộ -->
    <div v-if="activeTab === 'errors'">
      <div class="section card-sec mt-3" v-if="overview">
        <h3>{{ $t('bpdbAdmin.stuckOrdersTitle', { hours: overview.stuck_threshold_hours }) }}</h3>
        <table class="data-table" v-if="overview.stuck_links.length">
          <thead><tr><th>{{ $t('bpdbAdmin.colColorProduct') }}</th><th>{{ $t('bpdbAdmin.colMachineTank') }}</th><th>JIT</th><th>Adapter Status</th><th>{{ $t('bpdbAdmin.colCreatedAt') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in overview.stuck_links" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td>{{ row.jit_queue_code || '—' }}</td>
              <td><span class="status-badge">{{ row.adapter_status }}</span></td>
              <td>{{ formatTime(row.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">{{ $t('bpdbAdmin.noStuckOrders') }}</p>
      </div>

      <div class="section card-sec mt-3" v-if="overview">
        <h3>{{ $t('bpdbAdmin.ambiguousTitle') }}</h3>
        <table class="data-table" v-if="overview.ambiguous_links.length">
          <thead><tr><th>{{ $t('bpdbAdmin.colColorProduct') }}</th><th>{{ $t('bpdbAdmin.colMachineTank') }}</th><th>{{ $t('bpdbAdmin.colErrorNote') }}</th><th>{{ $t('bpdbAdmin.colCreatedAt') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in overview.ambiguous_links" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td class="font-xs">{{ row.bpdb_error_message }}</td>
              <td>{{ formatTime(row.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">{{ $t('bpdbAdmin.noAmbiguous') }}</p>
      </div>
    </div>

    <!-- Chemical task detail modal -->
    <div v-if="selectedTask" class="modal-overlay" @click.self="selectedTask = null">
      <div class="detail-drawer">
        <div class="flex-header">
          <h3>{{ $t('bpdbAdmin.modalTaskDetail', { machine: selectedTask.machineCode }) }}</h3>
          <button class="btn btn-secondary btn-sm" @click="selectedTask = null">{{ $t('common.close') }}</button>
        </div>
        <p class="font-xs text-muted mono-text-sm">{{ selectedTask.taskTitle }}</p>
        <p class="font-xs">
          {{ $t('common.status') }}: <strong class="op-status-badge" :class="'status-' + selectedTask.displayStatus.toLowerCase()">{{ demandStatusLabel(selectedTask.displayStatus) }}</strong>
          · Tank {{ selectedTask.tank || '—' }}
        </p>
        <p class="font-xs text-muted">
          {{ $t('bpdbAdmin.createdAtLabel') }} {{ formatTime(selectedTask.createdAt) }} · {{ $t('bpdbAdmin.startedAtLabel') }} {{ formatTime(selectedTask.workStartTime) }} · {{ $t('bpdbAdmin.finishedAtLabel') }} {{ formatTime(selectedTask.finishTime) }}
        </p>
        <p v-if="selectedTask.errorMessage" class="text-error font-xs">⚠️ {{ selectedTask.errorMessage }}</p>

        <h4 class="font-sm mt-2">{{ $t('bpdbAdmin.chemicalsTitle', { count: selectedTask.chemicalLines?.length || 0 }) }}</h4>
        <p class="font-xs" :class="selectedTask.chemicalLinesSource === 'SUP_Storico' ? 'text-success' : 'text-muted'">
          <span v-if="selectedTask.chemicalLinesSource === 'SUP_Storico'">{{ $t('bpdbAdmin.realDataNote') }}</span>
          <span v-else>{{ $t('bpdbAdmin.fallbackDataNote') }}</span>
        </p>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ $t('common.code') }}</th><th>{{ $t('common.name') }}</th><th>{{ $t('bpdbAdmin.colRequested') }}</th><th>{{ $t('bpdbAdmin.colActual') }}</th><th>{{ $t('bpdbAdmin.colUnit') }}</th><th>{{ $t('bpdbAdmin.colDerivedStatus') }}</th></tr></thead>
          <tbody>
            <tr v-for="l in selectedTask.chemicalLines" :key="l.orderNum">
              <td>{{ l.orderNum }}</td>
              <td>{{ l.chemicalCode }}</td>
              <td>{{ l.chemicalName || '—' }}</td>
              <td>{{ l.requestedAmount }}</td>
              <td>{{ l.dosedAmount }}</td>
              <td>{{ l.unit }}</td>
              <td class="font-xs">{{ l.lineStatusDerived }}</td>
            </tr>
            <tr v-if="!selectedTask.chemicalLines?.length"><td colspan="7" class="text-muted text-center">{{ $t('bpdbAdmin.noChemicalData') }}</td></tr>
          </tbody>
        </table>
        <p class="font-xs text-muted mt-1">{{ $t('bpdbAdmin.derivedStatusNote') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n({ useScope: 'global' });

const tabs = [
  { key: 'overview', labelKey: 'bpdbAdmin.tabOverview' },
  { key: 'chemicalDemand', labelKey: 'bpdbAdmin.tabChemicalDemand' },
  { key: 'materialActivity', labelKey: 'bpdbAdmin.tabMaterialActivity' },
  { key: 'machineFeeding', labelKey: 'bpdbAdmin.tabMachineFeeding' },
  { key: 'transport', labelKey: 'bpdbAdmin.tabTransport' },
  { key: 'jit', labelKey: 'bpdbAdmin.tabJit' },
  { key: 'taskLinks', labelKey: 'bpdbAdmin.tabTaskLinks' },
  { key: 'errors', labelKey: 'bpdbAdmin.tabErrors' },
];
const activeTab = ref('overview');

// Nhãn TRUNG TÍNH theo yêu cầu — không dùng "Đang bơm"/"Đã bơm xong" vì BPDB không phân
// biệt được cân/hòa tan/bơm vật lý và chưa có bằng chứng xác nhận đã cấp hóa chất xong
// (xem cảnh báo đầu tab). Phải khớp displayStatus trả về từ BpdbChemicalDemandService.
const DEMAND_STATUS_LABEL_KEYS: Record<string, string> = {
  AWAITING_PROCESSING: 'bpdbAdmin.demandStatusAwaitingProcessing',
  TRANSITIONING: 'bpdbAdmin.demandStatusTransitioning',
  PROCESSING: 'bpdbAdmin.demandStatusProcessing',
  ENDED: 'bpdbAdmin.demandStatusEnded',
  CANCELLED: 'bpdbAdmin.demandStatusCancelled',
  ERROR: 'bpdbAdmin.demandStatusError',
  UNKNOWN: 'bpdbAdmin.demandStatusUnknown',
};
const demandStatusLabel = (status: string) => {
  const key = DEMAND_STATUS_LABEL_KEYS[status];
  return key ? t(key) : status;
};

// Nhãn trung tính cho SUP_Tasks.TaskStatus thô (10/20/30/40/99) — dùng ở những chỗ còn
// hiển thị mã gốc thay vì displayStatus đã suy diễn (yêu cầu 2026-07-21, "CHỐT NGUỒN DỮ
// LIỆU"). Khớp App\Services\ColorService\BpdbTaskStatusLabels ở backend.
const RAW_TASK_STATUS_LABEL_KEYS: Record<string, string> = {
  '10': 'bpdbAdmin.rawStatus10',
  '20': 'bpdbAdmin.rawStatus20',
  '30': 'bpdbAdmin.rawStatus30',
  '40': 'bpdbAdmin.rawStatus40',
  '99': 'bpdbAdmin.rawStatus99',
};
const rawTaskStatusLabel = (status: string | number | null | undefined) => {
  if (status === null || status === undefined || status === '') return '—';
  const key = RAW_TASK_STATUS_LABEL_KEYS[String(status)];
  return key ? t(key) : t('bpdbAdmin.rawStatusUnknown', { status });
};

const overview = ref<any>(null);
const taskLinks = ref<any[]>([]);
const jitRules = ref<any[]>([]);
const machines = ref<any[]>([]);
const demandActive = ref<any[]>([]);
const demandCompleted = ref<any[]>([]);
const demandErrors = ref<any[]>([]);
const demandSummary = ref<any>(null);
const selectedTask = ref<any>(null);
const hideOrphaned = ref(true); // mặc định ẩn task tồn đọng, không xóa khỏi dữ liệu
const statusFilter = ref('');

// Tab "Thống kê hoạt động nguyên liệu" — mặc định 30 ngày gần nhất, khớp resolveRange() phía
// backend khi không truyền from/to.
const toDateInput = (d: Date) => d.toISOString().slice(0, 10);
const maTo = ref(toDateInput(new Date()));
const maFrom = ref(toDateInput(new Date(Date.now() - 30 * 24 * 3600 * 1000)));
const maMachineFilter = ref('');
const maSummary = ref<any>(null);
const maByMachine = ref<any[]>([]);
const maByMachineData = ref<any>(null);
const maByMaterial = ref<any[]>([]);
const maTimeseries = ref<any[]>([]);
const maDetail = ref<any[]>([]);
const maDetailTotal = ref(0);
const maPage = ref(1);
const maPerPage = 20;
const maLoading = ref(false);

const maMaxMachineCount = computed(() => Math.max(1, ...maByMachine.value.map((m: any) => m.completedTaskCount)));
const maMaxDayCount = computed(() => Math.max(1, ...maTimeseries.value.map((p: any) => p.completedTaskCount)));
const maBarWidth = (value: number, max: number) => `${Math.max(4, Math.round((value / max) * 100))}%`;
const maExportUrl = computed(() => {
  const params = new URLSearchParams({ from: maFrom.value, to: maTo.value });
  if (maMachineFilter.value) params.set('machine_code', maMachineFilter.value);
  return `/api/admin/bpdb/material-activity/detail/export?${params.toString()}`;
});

// Tab "Cấp máy" (Mức A — BPVN2025.SUP_Storico), cùng mặc định 30 ngày như "Thống kê hoạt
// động nguyên liệu".
const mfFrom = ref(toDateInput(new Date(Date.now() - 30 * 24 * 3600 * 1000)));
const mfTo = ref(toDateInput(new Date()));
const mfMachineFilter = ref('');
const mfSummary = ref<any>(null);
const mfByMachine = ref<any[]>([]);
const mfByMaterial = ref<any[]>([]);
const mfDetail = ref<any[]>([]);
const mfDetailTotal = ref(0);
const mfPage = ref(1);
const mfPerPage = 20;
const mfLoading = ref(false);
const mfExportUrl = computed(() => {
  const params = new URLSearchParams({ from: mfFrom.value, to: mfTo.value });
  if (mfMachineFilter.value) params.set('machine_code', mfMachineFilter.value);
  return `/api/admin/bpdb/machine-feeding/detail/export?${params.toString()}`;
});

const mfRangeParams = () => {
  const params: Record<string, string> = { from: mfFrom.value, to: mfTo.value };
  if (mfMachineFilter.value) params.machine_code = mfMachineFilter.value;
  return params;
};

const fetchMachineFeeding = async () => {
  mfLoading.value = true;
  try {
    const params = mfRangeParams();
    const [summaryRes, byMachineRes, byMaterialRes] = await Promise.all([
      axios.get('/api/admin/bpdb/machine-feeding/summary', { params }),
      axios.get('/api/admin/bpdb/machine-feeding/by-machine', { params: { from: params.from, to: params.to } }),
      axios.get('/api/admin/bpdb/machine-feeding/by-material', { params }),
    ]);
    mfSummary.value = summaryRes.data;
    mfByMachine.value = byMachineRes.data.machines;
    mfByMaterial.value = byMaterialRes.data.materials;
    mfPage.value = 1;
    await fetchMachineFeedingDetail();
  } finally {
    mfLoading.value = false;
  }
};

const fetchMachineFeedingDetail = async () => {
  const res = await axios.get('/api/admin/bpdb/machine-feeding/detail', {
    params: { ...mfRangeParams(), page: mfPage.value, per_page: mfPerPage },
  });
  mfDetail.value = res.data.rows;
  mfDetailTotal.value = res.data.total;
};

const changeMfPage = async (page: number) => {
  mfPage.value = page;
  await fetchMachineFeedingDetail();
};

// Tab "Theo dõi xử lý JIT" ("Vận chuyển", Mức B) — tái dùng nguyên `machines` đã fetch qua
// fetchMachines() (cùng nguồn dữ liệu với trang riêng "Máy VD" ở /bpdb-machines), KHÔNG
// gọi thêm API riêng (đúng nguyên tắc chỉ hiển thị dữ liệu có bằng chứng, không suy diễn
// thêm gì ngoài trạng thái task đã biết).
const transportRows = computed(() => machines.value.filter((m: any) => m.currentTask));

const loading = ref(false);
const errorMsg = ref('');
const bpdbConnected = ref(true);
const lastSyncedAt = ref<string | null>(null);
const dataAgeSeconds = ref(0);
const dataStale = ref(false);

// Tách 3 nhóm theo activityBucket (BpdbChemicalDemandService) — không lọc bằng ngưỡng lặp
// lại ở frontend, chỉ đọc cờ đã tính sẵn ở backend (nguồn ngưỡng duy nhất, cấu hình qua
// app.feature_flags).
const demandActiveRecent = computed(() => demandActive.value.filter(t => t.activityBucket === 'ACTIVE_RECENT'));
const demandStuck = computed(() => demandActive.value.filter(t => t.activityBucket === 'STUCK'));
const demandOrphaned = computed(() => demandActive.value.filter(t => t.activityBucket === 'ORPHANED_OLD'));
const orphanThresholdLabel = computed(() => {
  const hours = demandSummary.value?.orphanThresholdHours;
  return hours ? `${hours}h` : '24h';
});

const formatTime = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

const formatDuration = (seconds: number | null) => {
  if (seconds === null || seconds === undefined) return '—';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return t('bpdbAdmin.durationMinSec', { m, s });
};

const fetchOverview = async () => {
  const res = await axios.get('/api/admin/bpdb/overview');
  overview.value = res.data;
  bpdbConnected.value = res.data.bpdb_connection?.connected ?? false;
};

const fetchTaskLinks = async () => {
  const res = await axios.get('/api/admin/bpdb/task-links', {
    params: statusFilter.value ? { adapter_status: statusFilter.value } : {},
  });
  taskLinks.value = res.data.data;
};

const fetchJitRules = async () => {
  const res = await axios.get('/api/admin/bpdb/jit-routing-rules');
  jitRules.value = res.data.data;
};

const applyEnvelope = (data: any) => {
  lastSyncedAt.value = data.lastSyncedAt ?? null;
  dataAgeSeconds.value = Math.round(data.dataAgeSeconds ?? 0);
  dataStale.value = !!data.stale;
};

const fetchMachines = async () => {
  const res = await axios.get('/api/admin/bpdb/machines/status');
  machines.value = res.data.data;
  bpdbConnected.value = res.data.bpdbConnected;
  applyEnvelope(res.data);
};

const fetchChemicalDemand = async () => {
  const res = await axios.get('/api/admin/bpdb/chemical-demand');
  demandActive.value = res.data.active;
  demandCompleted.value = res.data.completedRecent;
  demandErrors.value = res.data.errorOrCancelled;
  bpdbConnected.value = res.data.bpdbConnected;
  applyEnvelope(res.data);
};

const fetchChemicalDemandSummary = async () => {
  const res = await axios.get('/api/admin/bpdb/chemical-demand/summary');
  demandSummary.value = res.data;
  bpdbConnected.value = res.data.bpdbConnected;
};

const openTaskDetail = async (taskId: string) => {
  const res = await axios.get(`/api/admin/bpdb/chemical-demand/${taskId}`);
  selectedTask.value = res.data.data;
};

const maRangeParams = () => {
  const params: Record<string, string> = { from: maFrom.value, to: maTo.value };
  if (maMachineFilter.value) params.machine_code = maMachineFilter.value;
  return params;
};

const fetchMaterialActivity = async () => {
  maLoading.value = true;
  try {
    const params = maRangeParams();
    const [summaryRes, byMachineRes, byMaterialRes, timeseriesRes] = await Promise.all([
      axios.get('/api/admin/bpdb/material-activity/summary', { params }),
      axios.get('/api/admin/bpdb/material-activity/by-machine', { params: { from: params.from, to: params.to } }),
      axios.get('/api/admin/bpdb/material-activity/by-material', { params }),
      axios.get('/api/admin/bpdb/material-activity/timeseries', { params: { ...params, granularity: 'day' } }),
    ]);
    maSummary.value = summaryRes.data;
    maByMachineData.value = byMachineRes.data;
    maByMachine.value = byMachineRes.data.machines;
    maByMaterial.value = byMaterialRes.data.materials;
    maTimeseries.value = timeseriesRes.data.points;
    maPage.value = 1;
    await fetchMaterialActivityDetail();
  } finally {
    maLoading.value = false;
  }
};

const fetchMaterialActivityDetail = async () => {
  const res = await axios.get('/api/admin/bpdb/material-activity/detail', {
    params: { ...maRangeParams(), page: maPage.value, per_page: maPerPage },
  });
  maDetail.value = res.data.rows;
  maDetailTotal.value = res.data.total;
};

const changeMaPage = async (page: number) => {
  maPage.value = page;
  await fetchMaterialActivityDetail();
};

const fetchActiveTab = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    if (activeTab.value === 'overview') await fetchOverview();
    else if (activeTab.value === 'taskLinks') await fetchTaskLinks();
    else if (activeTab.value === 'chemicalDemand') await Promise.all([fetchChemicalDemand(), fetchChemicalDemandSummary()]);
    else if (activeTab.value === 'materialActivity') await fetchMaterialActivity();
    else if (activeTab.value === 'machineFeeding') await fetchMachineFeeding();
    else if (activeTab.value === 'transport') await fetchMachines();
    else if (activeTab.value === 'jit') await fetchJitRules();
    else if (activeTab.value === 'errors') await fetchOverview();
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || t('bpdbAdmin.loadError');
  } finally {
    loading.value = false;
  }
};

// Chỉ tải dữ liệu của tab đang mở, KHÔNG tải trước cả 6 tab ngay khi vào trang (2026-07-20
// — sửa sau khi phát hiện `php artisan serve` xử lý tuần tự từng request một, tải trước 9
// API cùng lúc lúc mount làm trang vào chậm hẳn, đặc biệt khi có nhiều tab trình duyệt cùng
// mở trang này). Chuyển tab mới gọi API của tab đó (lazy-load qua switchTab()).
const switchTab = (key: string) => {
  activeTab.value = key;
  fetchActiveTab();
};

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
  await fetchActiveTab();
  // Cập nhật gần thời gian thực cho tab "Theo dõi xử lý JIT" / "Nhu cầu bơm hóa chất" — 5s
  // theo phương án MVP (mục 8), backend đã cache 4s nên nhiều trình duyệt cùng mở không
  // dội query trực tiếp vào BPDB.
  pollTimer = setInterval(() => {
    if (activeTab.value === 'transport') {
      fetchMachines();
    } else if (activeTab.value === 'chemicalDemand') {
      fetchChemicalDemand();
      fetchChemicalDemandSummary();
    }
  }, 5000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.bpdb-admin-container { padding: 1rem; }

.tab-bar {
  display: flex;
  gap: 0.25rem;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.tab-btn {
  background: none;
  border: none;
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  border-bottom: 2px solid transparent;
}

.tab-btn.active {
  color: #4f46e5;
  border-bottom-color: #4f46e5;
  font-weight: 600;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
  margin-top: 1rem;
}

.summary-card {
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 8px;
  padding: 0.75rem;
  text-align: center;
}

.summary-label {
  font-size: 0.72rem;
  color: var(--text-muted, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.summary-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 0.25rem;
}

.status-dist {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.status-chip {
  background: var(--bg-main, #f3f4f6);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 6px;
  padding: 0.25rem 0.6rem;
  font-size: 0.8rem;
}

.status-badge {
  background: rgba(99, 102, 241, 0.12);
  color: #4f46e5;
  border-radius: 4px;
  padding: 0.1rem 0.4rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin-top: 0.5rem;
}

.data-table th, .data-table td {
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  padding: 0.4rem 0.5rem;
  text-align: left;
}

.mono-text-sm { font-family: monospace; font-size: 0.75rem; }
.font-xs { font-size: 0.72rem; }

.stale-banner {
  background: rgba(202, 138, 4, 0.12);
  border: 1px solid rgba(202, 138, 4, 0.3);
  color: #92400e;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.82rem;
}
.stale-banner.error-banner {
  background: rgba(220, 38, 38, 0.1);
  border-color: rgba(220, 38, 38, 0.3);
  color: #991b1b;
}

.connection-disclaimer {
  background: var(--bg-main, #f3f4f6);
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  color: var(--text-muted, #6b7280);
  margin-top: 0.35rem;
}
.flex-header { display: flex; justify-content: space-between; align-items: center; }

.op-status-badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  background: rgba(156,163,175,0.15);
  color: #6b7280;
}
.op-status-badge.status-processing { background: rgba(37,99,235,0.12); color: #2563eb; }
.op-status-badge.status-waiting, .op-status-badge.status-awaiting_processing { background: rgba(202,138,4,0.12); color: #ca8a04; }
.op-status-badge.status-transitioning { background: rgba(147,51,234,0.12); color: #9333ea; }
.op-status-badge.status-completed_recently, .op-status-badge.status-ended { background: rgba(22,163,74,0.12); color: #16a34a; }
.op-status-badge.status-cancelled, .op-status-badge.status-error { background: rgba(220,38,38,0.12); color: #dc2626; }

.ma-bar-cell { display: flex; align-items: center; gap: 0.5rem; min-width: 120px; }
.ma-bar { height: 0.55rem; border-radius: 3px; background: #4f46e5; opacity: 0.75; min-width: 3px; }
.ma-bar-cell span { font-variant-numeric: tabular-nums; font-size: 0.78rem; }

.demand-row { cursor: pointer; }
.demand-row:hover { background: var(--bg-main, #f3f4f6); }
.demand-row.demand-processing td:first-child { border-left: 3px solid #2563eb; }
.demand-row.demand-transitioning td:first-child { border-left: 3px solid #9333ea; }
.demand-row.demand-awaiting_processing td:first-child { border-left: 3px solid #ca8a04; }

.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 50;
}
.detail-drawer {
  background: var(--bg-card, #fff); border-radius: 10px; padding: 1.2rem;
  width: 90%; max-width: 720px; max-height: 85vh; overflow-y: auto;
}
</style>
