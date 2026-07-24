<template>
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    :width="size" 
    :height="size" 
    viewBox="0 0 24 24" 
    fill="none" 
    :stroke="color || 'currentColor'" 
    stroke-width="2" 
    stroke-linecap="round" 
    stroke-linejoin="round" 
    class="svg-icon"
  >
    <!-- dashboard / home -->
    <path v-if="name === 'dashboard'" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
    <polyline v-if="name === 'dashboard'" points="9 22 9 12 15 12 15 22" />

    <!-- batch / box -->
    <path v-if="name === 'batch'" d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
    <path v-if="name === 'batch'" d="M3.3 7 12 12l8.7-5" />
    <path v-if="name === 'batch'" d="M12 22V12" />

    <!-- scale / balance -->
    <path v-if="name === 'scale'" d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1" />
    <path v-if="name === 'scale'" d="M3 16V3a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v13" />
    <line v-if="name === 'scale'" x1="8" y1="21" x2="8" y2="18" />
    <line v-if="name === 'scale'" x1="12" y1="21" x2="12" y2="18" />
    <line v-if="name === 'scale'" x1="3" y1="8" x2="17" y2="8" />

    <!-- transfer / truck -->
    <rect v-if="name === 'transfer'" x="1" y="3" width="15" height="13" rx="2" ry="2" />
    <polygon v-if="name === 'transfer'" points="16 8 20 8 23 11 23 16 16 16 16 8" />
    <circle v-if="name === 'transfer'" cx="5.5" cy="18.5" r="2.5" />
    <circle v-if="name === 'transfer'" cx="18.5" cy="18.5" r="2.5" />

    <!-- feed / droplets -->
    <path v-if="name === 'feed'" d="M12 22a7 7 0 0 0 7-7c0-4.3-7-11-7-11S5 10.7 5 15a7 7 0 0 0 7 7z" />

    <!-- queue / list-ordered -->
    <line v-if="name === 'queue'" x1="10" y1="6" x2="21" y2="6" />
    <line v-if="name === 'queue'" x1="10" y1="12" x2="21" y2="12" />
    <line v-if="name === 'queue'" x1="10" y1="18" x2="21" y2="18" />
    <path v-if="name === 'queue'" d="M4 6h1v4" />
    <path v-if="name === 'queue'" d="M4 10h2" />
    <path v-if="name === 'queue'" d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" />

    <!-- material / flask / vial -->
    <path v-if="name === 'material'" d="M6 3h12" />
    <path v-if="name === 'material'" d="M12 3v14" />
    <path v-if="name === 'material'" d="M9 21h6" />
    <path v-if="name === 'material'" d="M10 3v5c0 1.2-1.3 2.5-2.5 3.5C5.8 13 5 15 5 17c0 2.2 1.8 4 4 4h6c2.2 0 4-1.8 4-4 0-2-.8-4-2.5-5.5C15.3 10.5 14 9.2 14 8V3z" />

    <!-- water / waves -->
    <path v-if="name === 'water'" d="M2 6c.6 0 1.2-.4 1.4-1 1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1s1.2-.4 1.4-1c1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1" />
    <path v-if="name === 'water'" d="M2 12c.6 0 1.2-.4 1.4-1 1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1s1.2-.4 1.4-1c1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1" />
    <path v-if="name === 'water'" d="M2 18c.6 0 1.2-.4 1.4-1 1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1s1.2-.4 1.4-1c1.3-3 5.9-3 7.2 0 .2.6.8 1 1.4 1" />

    <!-- recipe / file-text -->
    <path v-if="name === 'recipe'" d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
    <polyline v-if="name === 'recipe'" points="14 2 14 8 20 8" />
    <line v-if="name === 'recipe'" x1="16" y1="13" x2="8" y2="13" />
    <line v-if="name === 'recipe'" x1="16" y1="17" x2="8" y2="17" />
    <line v-if="name === 'recipe'" x1="10" y1="9" x2="8" y2="9" />

    <!-- alert / info / alert-circle -->
    <circle v-if="name === 'alert'" cx="12" cy="12" r="10" />
    <line v-if="name === 'alert'" x1="12" y1="8" x2="12" y2="12" />
    <line v-if="name === 'alert'" x1="12" y1="16" x2="12.01" y2="16" />

    <!-- search -->
    <circle v-if="name === 'search'" cx="11" cy="11" r="8" />
    <line v-if="name === 'search'" x1="21" y1="21" x2="16.65" y2="16.65" />

    <!-- user -->
    <path v-if="name === 'user'" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
    <circle v-if="name === 'user'" cx="12" cy="7" r="4" />

    <!-- logout -->
    <path v-if="name === 'logout'" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
    <polyline v-if="name === 'logout'" points="16 17 21 12 16 7" />
    <line v-if="name === 'logout'" x1="21" y1="12" x2="9" y2="12" />

    <!-- settings -->
    <circle v-if="name === 'settings'" cx="12" cy="12" r="3" />
    <path v-if="name === 'settings'" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />

    <!-- close / cross -->
    <line v-if="name === 'close'" x1="18" y1="6" x2="6" y2="18" />
    <line v-if="name === 'close'" x1="6" y1="6" x2="18" y2="18" />

    <!-- plus -->
    <line v-if="name === 'plus'" x1="12" y1="5" x2="12" y2="19" />
    <line v-if="name === 'plus'" x1="5" y1="12" x2="19" y2="12" />

    <!-- check -->
    <polyline v-if="name === 'check'" points="20 6 9 17 4 12" />

    <!-- arrow-left / chevron-left -->
    <polyline v-if="name === 'arrow-left'" points="15 18 9 12 15 6" />

    <!-- arrow-right / chevron-right -->
    <polyline v-if="name === 'arrow-right'" points="9 18 15 12 9 6" />

    <!-- menu / hamburger -->
    <line v-if="name === 'menu'" x1="3" y1="12" x2="21" y2="12" />
    <line v-if="name === 'menu'" x1="3" y1="6" x2="21" y2="6" />
    <line v-if="name === 'menu'" x1="3" y1="18" x2="21" y2="18" />

    <!-- bell / notification -->
    <path v-if="name === 'bell'" d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
    <path v-if="name === 'bell'" d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />

    <!-- tool / troubleshooting -->
    <path v-if="name === 'tool'" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />

    <!-- report / bar-chart -->
    <line v-if="name === 'report'" x1="18" y1="20" x2="18" y2="10" />
    <line v-if="name === 'report'" x1="12" y1="20" x2="12" y2="4" />
    <line v-if="name === 'report'" x1="6" y1="20" x2="6" y2="14" />

    <!-- audit / clipboard-list -->
    <path v-if="name === 'audit'" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
    <rect v-if="name === 'audit'" x="8" y="2" width="8" height="4" rx="1" ry="1" />
    <line v-if="name === 'audit'" x1="9" y1="12" x2="15" y2="12" />
    <line v-if="name === 'audit'" x1="9" y1="16" x2="15" y2="16" />
    <line v-if="name === 'audit'" x1="9" y1="20" x2="12" y2="20" />

    <!-- sun (theme sáng) -->
    <circle v-if="name === 'sun'" cx="12" cy="12" r="4" />
    <line v-if="name === 'sun'" x1="12" y1="2" x2="12" y2="4" />
    <line v-if="name === 'sun'" x1="12" y1="20" x2="12" y2="22" />
    <line v-if="name === 'sun'" x1="4.93" y1="4.93" x2="6.34" y2="6.34" />
    <line v-if="name === 'sun'" x1="17.66" y1="17.66" x2="19.07" y2="19.07" />
    <line v-if="name === 'sun'" x1="2" y1="12" x2="4" y2="12" />
    <line v-if="name === 'sun'" x1="20" y1="12" x2="22" y2="12" />
    <line v-if="name === 'sun'" x1="4.93" y1="19.07" x2="6.34" y2="17.66" />
    <line v-if="name === 'sun'" x1="17.66" y1="6.34" x2="19.07" y2="4.93" />

    <!-- moon (theme tối) -->
    <path v-if="name === 'moon'" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />

    <!-- download -->
    <path v-if="name === 'download'" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
    <polyline v-if="name === 'download'" points="7 10 12 15 17 10" />
    <line v-if="name === 'download'" x1="12" y1="15" x2="12" y2="3" />
  </svg>
</template>

<script setup lang="ts">
defineProps({
  name: {
    type: String,
    required: true
  },
  size: {
    type: [Number, String],
    default: 20
  },
  color: {
    type: String,
    default: ''
  }
});
</script>

<style scoped>
.svg-icon {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
}
</style>
