<template>
  <!-- Công tắc NGUỒN CÂN trên thanh trên — chỉ hiện khi đang mở một màn trạm cân (nguonCan.kind).
       `compact` = bản cho dải thu gọn 24px (mặc định của tài khoản cannho/canto). -->
  <div v-if="nguonCan.kind" class="src-switch" :class="{ compact }">
    <span v-if="!compact" class="src-label">{{ $t('scaleSourceSwitch.label') }}</span>
    <div class="src-seg">
      <button
        :class="{ on: nguonCan.mode === 'PUTTY_LOG' }"
        :disabled="dangDoi || nguonCan.khoa"
        :title="nguonCan.khoa ? $t('scaleSourceSwitch.busyTitle') : $t('scaleSourceSwitch.puttyTitle')"
        @click="onDoi('PUTTY_LOG')"
      >{{ $t('scaleSourceSwitch.putty') }}</button>
      <button
        :class="{ on: nguonCan.mode === 'AUTO_COM' }"
        :disabled="dangDoi || nguonCan.khoa"
        :title="nguonCan.khoa ? $t('scaleSourceSwitch.busyTitle') : $t('scaleSourceSwitch.autoTitle')"
        @click="onDoi('AUTO_COM')"
      >{{ $t('scaleSourceSwitch.auto') }}</button>
    </div>
    <!-- DÒ CÂN: tìm lại cổng COM của cân rồi cố định cổng đó. Chỉ cần bấm khi cắm cáp sang lỗ USB
         khác (Windows đổi số COM) — bình thường Agent mở thẳng cổng đã cố định, không dò. -->
    <button
      class="src-rescan"
      :disabled="dangDoi || nguonCan.khoa"
      :title="nguonCan.khoa ? $t('scaleSourceSwitch.busyTitle') : $t('scaleSourceSwitch.rescanTitle')"
      @click="onDoLai"
    >{{ $t('scaleSourceSwitch.rescan') }}</button>
    <span class="src-info" :class="{ bad: !!loi }" :title="loi || nguonCan.msg || ''">{{ loi || moTa }}</span>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { nguonCan, doiNguonCan, type CheDoNguonCan } from '../services/scaleSource';

defineProps<{ compact?: boolean }>();

const { t } = useI18n({ useScope: 'global' });
const dangDoi = ref(false);
const loi = ref('');

const moTa = computed(() => {
  if (!nguonCan.mode) return t('scaleSourceSwitch.unknown');
  if (nguonCan.port) return t('scaleSourceSwitch.readingPort', { port: nguonCan.port });
  return nguonCan.msg || '';
});

async function onDoLai() {
  if (dangDoi.value) return;
  dangDoi.value = true;
  loi.value = '';
  try {
    await doiNguonCan('AUTO_COM', true);
  } catch (e: any) {
    loi.value = t(`scaleSourceSwitch.${MA_LOI[e?.message] || 'errRejected'}`);
  } finally {
    dangDoi.value = false;
  }
}

const MA_LOI: Record<string, string> = {
  AGENT_UNREACHABLE: 'errUnreachable',
  AGENT_TOO_OLD: 'errTooOld',
  ORIGIN_FORBIDDEN: 'errOrigin',
};

async function onDoi(mode: CheDoNguonCan) {
  if (nguonCan.mode === mode || dangDoi.value) return;
  dangDoi.value = true;
  loi.value = '';
  try {
    await doiNguonCan(mode);
  } catch (e: any) {
    loi.value = t(`scaleSourceSwitch.${MA_LOI[e?.message] || 'errRejected'}`);
  } finally {
    dangDoi.value = false;
  }
}
</script>

<style scoped>
.src-switch {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.src-label {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--text-muted);
  white-space: nowrap;
}

/* Cùng kiểu viên thuốc với bộ chọn ngôn ngữ bên cạnh (.lang-switch trong AppLayout). */
.src-seg {
  display: flex;
  align-items: center;
  gap: 2px;
  height: 36px;
  padding: 3px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  flex-shrink: 0;
}

.src-seg button {
  height: 100%;
  padding: 0 10px;
  border: none;
  border-radius: var(--radius-full);
  background: none;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  font-family: inherit;
}

.src-seg button:hover:not(:disabled) { color: var(--text-title); }
.src-seg button.on { background-color: var(--primary-bg); color: var(--primary-hover); }
.src-seg button:disabled { cursor: not-allowed; opacity: 0.55; }
.src-seg button.on:disabled { opacity: 0.85; }

.src-rescan {
  height: 36px;
  padding: 0 12px;
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  color: var(--text-title);
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  font-family: inherit;
  flex-shrink: 0;
}
.src-rescan:hover:not(:disabled) { border-color: var(--primary-hover); color: var(--primary-hover); }
.src-rescan:disabled { cursor: not-allowed; opacity: 0.55; }

.src-info {
  max-width: 220px;
  font-size: 12px;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.src-info.bad { color: #d93025; font-weight: 700; }

/* Dải thu gọn chỉ cao 24px. */
.compact { gap: 6px; }
.compact .src-seg { height: 20px; padding: 1px; }
.compact .src-seg button { padding: 0 8px; font-size: 11px; }
.compact .src-rescan { height: 20px; padding: 0 8px; font-size: 11px; }
.compact .src-info { font-size: 11px; max-width: 260px; }
</style>
