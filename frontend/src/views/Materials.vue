<template>
  <div class="dashboard-container">
    <!-- Main Content -->
    <main class="main-content">
      <section class="section">
        <div class="section-header">
          <h3>🧪 Quản Lý Danh Mục Vật Tư, Thuốc Nhuộm & Hóa Chất</h3>
          <div class="header-actions">
            <input 
              id="material-search-input"
              v-model="searchQuery" 
              @input="handleSearch"
              type="text" 
              placeholder="🔍 Tìm mã hoặc tên vật tư..." 
              class="form-input search-box"
            />
            <select 
              id="material-type-filter"
              v-model="selectedType" 
              @change="fetchMaterials" 
              class="form-select filter-select"
            >
              <option value="">Tất cả phân loại</option>
              <option value="DYE">DYE (Bột màu nhuộm)</option>
              <option value="CHEMICAL">CHEMICAL (Hóa chất/Dung môi)</option>
              <option value="ADDITIVE">ADDITIVE (Phụ gia/Chất trợ)</option>
            </select>
          </div>
        </div>

        <!-- Materials Table -->
        <div class="table-container">
          <table class="stats-table">
            <thead>
              <tr>
                <th>Mã Vật Tư</th>
                <th>Tên Nguyên Liệu</th>
                <th>Loại</th>
                <th>Nhà Cung Cấp</th>
                <th class="number-cell">Tồn Kho (g)</th>
                <th>Trạng Thái</th>
                <th class="action-cell">Thao Tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mat in materials" :key="mat.id" class="table-row-hover">
                <td class="bold-text highlight-code">{{ mat.code }}</td>
                <td>{{ mat.name }}</td>
                <td>
                  <span :class="getTypeClass(mat.type)">{{ mat.type }}</span>
                </td>
                <td>{{ mat.supplier || 'N/A' }}</td>
                <td class="number-cell bold-text text-glow">{{ mat.stock_qty.toLocaleString() }} g</td>
                <td>
                  <span :class="mat.is_active ? 'badge-active' : 'badge-inactive'">
                    {{ mat.is_active ? 'HOẠT ĐỘNG' : 'TẠM KHÓA' }}
                  </span>
                </td>
                <td class="action-cell">
                  <button 
                    :id="'edit-btn-' + mat.code"
                    @click="openEditModal(mat)" 
                    class="edit-action-btn"
                  >
                    ✏️ Sửa
                  </button>
                </td>
              </tr>
              <tr v-if="materials.length === 0">
                <td colspan="7" class="empty-cell">Không tìm thấy vật tư nào phù hợp.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-footer" v-if="totalPages > 1">
          <button 
            id="prev-page-btn"
            @click="changePage(currentPage - 1)" 
            :disabled="currentPage === 1" 
            class="page-btn"
          >
            ◀ Trước
          </button>
          <span class="page-info">Trang {{ currentPage }} / {{ totalPages }} (Tổng {{ totalItems }} dòng)</span>
          <button 
            id="next-page-btn"
            @click="changePage(currentPage + 1)" 
            :disabled="currentPage === totalPages" 
            class="page-btn"
          >
            Sau ▶
          </button>
        </div>
      </section>
    </main>

    <!-- Edit Modal -->
    <div class="modal-backdrop" v-if="showModal">
      <div class="modal-card">
        <div class="modal-header">
          <h4>✏️ Cập Nhật Vật Tư: {{ editingMaterial?.code }}</h4>
          <button @click="closeModal" class="close-modal-btn">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="edit-mat-name">Tên nguyên liệu</label>
            <input 
              id="edit-mat-name"
              v-model="editForm.name" 
              type="text" 
              class="form-input"
            />
          </div>
          <div class="form-group">
            <label for="edit-mat-stock">Tồn kho hiện tại (g)</label>
            <input 
              id="edit-mat-stock"
              v-model.number="editForm.stock_qty" 
              type="number" 
              class="form-input"
            />
          </div>
          <div class="form-group row-group">
            <label for="edit-mat-active">Trạng thái hoạt động</label>
            <input 
              id="edit-mat-active"
              v-model="editForm.is_active" 
              type="checkbox" 
              class="form-checkbox"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button id="cancel-modal-btn" @click="closeModal" class="cancel-btn">Hủy</button>
          <button id="save-modal-btn" @click="saveMaterial" class="submit-btn" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu Thay Đổi' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();

// State
const materials = ref<any[]>([]);
const searchQuery = ref('');
const selectedType = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

const showModal = ref(false);
const editingMaterial = ref<any>(null);
const editForm = ref({ name: '', stock_qty: 0, is_active: true });
const saving = ref(false);

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};

const getTypeClass = (type: string) => {
  if (type === 'DYE') return 'tag-dye';
  if (type === 'CHEMICAL') return 'tag-chemical';
  return 'tag-additive';
};

// Fetch list
const fetchMaterials = async () => {
  try {
    const token = authStore.token;
    const response = await axios.get('/api/materials', {
      headers: { Authorization: `Bearer ${token}` },
      params: {
        page: currentPage.value,
        search: searchQuery.value || undefined,
        type: selectedType.value || undefined
      }
    });
    materials.value = response.data.data;
    currentPage.value = response.data.current_page;
    totalPages.value = response.data.last_page;
    totalItems.value = response.data.total;
  } catch (error) {
    console.error('Fetch materials error:', error);
  }
};

let searchTimeout: any = null;
const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchMaterials();
  }, 300);
};

const changePage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchMaterials();
  }
};

// Modal Actions
const openEditModal = (mat: any) => {
  editingMaterial.value = mat;
  editForm.value = {
    name: mat.name,
    stock_qty: mat.stock_qty,
    is_active: mat.is_active
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingMaterial.value = null;
};

const saveMaterial = async () => {
  if (!editingMaterial.value) return;
  saving.value = true;
  try {
    const token = authStore.token;
    await axios.put(`/api/materials/${editingMaterial.value.id}`, editForm.value, {
      headers: { Authorization: `Bearer ${token}` }
    });
    fetchMaterials();
    closeModal();
  } catch (error) {
    console.error('Save material error:', error);
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchMaterials();
});
</script>

<style scoped>
/* Scoped custom styling for premium feel */
.nav-tabs {
  display: flex;
  gap: 1rem;
  margin: 0 1.5rem;
}
.nav-item {
  color: var(--text-subtitle);
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: all 0.2s ease;
}
.nav-item:hover {
  color: var(--text-title);
  background: var(--bg-tag);
}
.nav-item.active {
  color: var(--text-title);
  background: var(--primary);
  box-shadow: 0 0 12px var(--primary-shadow);
}
.header-actions {
  display: flex;
  gap: 1rem;
}
.search-box {
  width: 250px;
}
.filter-select {
  width: 200px;
}
.tag-dye {
  background: hsla(280, 70%, 25%, 0.4);
  color: hsl(280, 85%, 75%);
  border: 1px solid hsla(280, 60%, 40%, 0.4);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
}
.tag-chemical {
  background: hsla(200, 70%, 25%, 0.4);
  color: hsl(200, 85%, 75%);
  border: 1px solid hsla(200, 60%, 40%, 0.4);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
}
.tag-additive {
  background: hsla(140, 70%, 25%, 0.4);
  color: hsl(140, 85%, 75%);
  border: 1px solid hsla(140, 60%, 40%, 0.4);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
}
.badge-active {
  color: var(--success);
  font-weight: 600;
}
.badge-inactive {
  color: var(--danger-text);
  font-weight: 600;
}
.edit-action-btn {
  background: transparent;
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.edit-action-btn:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.highlight-code {
  color: hsl(35, 100%, 65%);
}
.text-glow {
  text-shadow: 0 0 8px rgba(255, 255, 255, 0.1);
}
.table-row-hover:hover {
  background: hsla(224, 20%, 22%, 0.5);
}
.pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-divider);
}
.page-btn {
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.page-btn:hover:not(:disabled) {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Modal Backing */
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
  width: 450px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
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
.row-group {
  flex-direction: row !important;
  justify-content: space-between;
  align-items: center;
}
.form-checkbox {
  width: 20px;
  height: 20px;
  accent-color: var(--primary);
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
</style>
