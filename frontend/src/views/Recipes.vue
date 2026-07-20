<template>
  <div class="dashboard-container">
    <!-- Main Content -->
    <main class="main-content">
      <!-- Mode Toggle Header -->
      <div class="section-header mode-toggle-header">
        <div class="mode-buttons">
          <button 
            id="list-mode-btn"
            @click="activeMode = 'list'" 
            :class="['mode-btn', activeMode === 'list' ? 'active' : '']"
          >
            📋 Danh sách công thức
          </button>
          <button 
            id="create-mode-btn"
            @click="activeMode = 'create'" 
            :class="['mode-btn', activeMode === 'create' ? 'active' : '']"
          >
            ➕ Soạn thảo công thức mới
          </button>
        </div>
        
        <div v-if="activeMode === 'list'" class="header-actions">
          <input 
            id="recipe-search-input"
            v-model="searchQuery" 
            @input="handleSearch"
            type="text" 
            placeholder="🔍 Tìm mã màu hoặc mã hàng..." 
            class="form-input search-box"
          />
        </div>
      </div>

      <!-- MODE 1: LIST RECIPES -->
      <div v-if="activeMode === 'list'" class="recipes-list-area">
        <div class="recipes-grid">
          <div v-for="recipe in recipes" :key="recipe.id" class="recipe-card">
            <div class="recipe-card-header">
              <div class="recipe-title">
                <span class="recipe-color-tag">{{ recipe.color_code }}</span>
                <h4>Mã hàng: {{ recipe.product_code }}</h4>
              </div>
              <span class="version-tag">v{{ recipe.latest_version?.version || 1 }}</span>
            </div>
            
            <div class="recipe-card-body">
              <p class="recipe-desc">{{ recipe.description || 'Không có mô tả.' }}</p>
              
              <div class="recipe-meta-row">
                <span class="meta-item">🎯 Lực căng: <strong>{{ recipe.latest_version?.parameters?.tension_value || 'Mặc định' }}</strong></span>
                <span class="meta-item">💧 Trùng nước: <strong>{{ recipe.latest_version?.parameters?.water_ratio_override ? '1:' + recipe.latest_version?.parameters?.water_ratio_override : 'Theo ma trận' }}</strong></span>
              </div>
              
              <!-- Materials list summary -->
              <div class="materials-list-summary">
                <h5>🧪 Thành phần:</h5>
                <ul>
                  <li v-for="mat in recipe.latest_version?.materials" :key="mat.id">
                    <span class="mat-code">{{ mat.material_code }}</span> - {{ mat.material?.name || 'Vật tư' }}
                    <span class="mat-conc">({{ mat.concentration }} g/l) [{{ mat.process_code }}]</span>
                  </li>
                </ul>
              </div>
            </div>
            
            <div class="recipe-card-footer">
              <button 
                :id="'calc-btn-' + recipe.color_code"
                @click="openCalculator(recipe)" 
                class="calc-action-btn"
              >
                🧮 Tính thử lượng cân (Simulate)
              </button>
            </div>
          </div>
          
          <div v-if="recipes.length === 0" class="empty-card">
            Không tìm thấy công thức nào phù hợp.
          </div>
        </div>
        
        <!-- Pagination -->
        <div class="pagination-footer" v-if="totalPages > 1">
          <button 
            id="recipe-prev-page-btn"
            @click="changePage(currentPage - 1)" 
            :disabled="currentPage === 1" 
            class="page-btn"
          >
            ◀ Trước
          </button>
          <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
          <button 
            id="recipe-next-page-btn"
            @click="changePage(currentPage + 1)" 
            :disabled="currentPage === totalPages" 
            class="page-btn"
          >
            Sau ▶
          </button>
        </div>
      </div>

      <!-- MODE 2: CREATE RECIPE FORM -->
      <div v-else class="recipes-create-area">
        <div class="two-col-grid">
          <!-- Col 1: Soạn thảo -->
          <div class="section card-sec">
            <h3>📝 Bản thảo Công thức</h3>
            <div class="control-form">
              <div class="two-col-form-row">
                <div class="form-group">
                  <label for="new-color-code">Mã màu (Color Code)</label>
                  <input 
                    id="new-color-code"
                    v-model="newRecipe.color_code" 
                    type="text" 
                    placeholder="Ví dụ: A+110293, HS51250"
                    class="form-input"
                  />
                </div>
                <div class="form-group">
                  <label for="new-product-code">Mã sản phẩm (Product Code)</label>
                  <input 
                    id="new-product-code"
                    v-model="newRecipe.product_code" 
                    type="text" 
                    placeholder="Ví dụ: T7400, L19029"
                    class="form-input"
                  />
                </div>
              </div>

              <div class="two-col-form-row">
                <div class="form-group">
                  <label for="new-tension">Lực căng đai (Tension)</label>
                  <input 
                    id="new-tension"
                    v-model.number="newRecipe.tension_value" 
                    type="number" 
                    step="0.1"
                    placeholder="Bỏ trống nếu mặc định"
                    class="form-input"
                  />
                </div>
                <div class="form-group">
                  <label for="new-water-override">Ghi đè tỉ lệ nước (Dung tỉ 1:X)</label>
                  <input 
                    id="new-water-override"
                    v-model.number="newRecipe.water_ratio_override" 
                    type="number" 
                    step="0.1"
                    placeholder="Ví dụ: 1.1 cho vải trắng"
                    class="form-input"
                  />
                </div>
              </div>

              <div class="form-group">
                <label for="new-desc">Mô tả / Ghi chú công nghệ</label>
                <textarea 
                  id="new-desc"
                  v-model="newRecipe.description" 
                  class="form-textarea" 
                  rows="2"
                ></textarea>
              </div>

              <!-- Materials lines -->
              <div class="materials-form-section">
                <div class="materials-header">
                  <h5>🧪 Danh sách thành phần nguyên liệu</h5>
                  <button @click="addMaterialRow" class="add-row-btn">+ Thêm dòng</button>
                </div>
                
                <div v-for="(row, idx) in newRecipe.materials" :key="idx" class="material-row">
                  <div class="form-group flex-1">
                    <label>Mã nguyên liệu</label>
                    <input 
                      v-model="row.material_code" 
                      type="text" 
                      placeholder="Mã (Y1008A, AC68...)"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group width-90">
                    <label>Nồng độ (g/l)</label>
                    <input 
                      v-model.number="row.concentration" 
                      type="number" 
                      step="0.0001"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group width-90">
                    <label>Công đoạn</label>
                    <select v-model="row.process_code" class="form-select">
                      <option value="P">P</option>
                      <option value="S">S</option>
                      <option value="+">+</option>
                      <option value="W">W</option>
                      <option value="HS">HS</option>
                    </select>
                  </div>
                  <div class="form-group width-60">
                    <label>Thùng</label>
                    <input 
                      v-model.number="row.tank_number" 
                      type="number" 
                      min="1" 
                      max="10"
                      class="form-input"
                    />
                  </div>
                  <button @click="removeMaterialRow(idx)" class="remove-row-btn">✕</button>
                </div>
              </div>

              <button id="save-recipe-btn" @click="saveRecipe" class="submit-btn" :disabled="saving">
                {{ saving ? 'Đang lưu...' : '💾 Lưu và Kích hoạt Công thức' }}
              </button>
              <p v-if="message" class="text-success">{{ message }}</p>
            </div>
          </div>

          <!-- Col 2: Calculator / Simulator -->
          <div class="section card-sec">
            <h3>🧮 Trình Giả Lập Định Lượng (Simulate & Verify)</h3>
            <p class="section-desc">Kiểm thử nhanh lượng nước và trọng lượng bột màu được làm tròn tự động bằng API.</p>
            
            <div class="control-form">
              <div class="two-col-form-row">
                <div class="form-group">
                  <label for="sim-weight">Khối lượng vải đai (kg)</label>
                  <input 
                    id="sim-weight"
                    v-model.number="simForm.weight" 
                    type="number" 
                    step="0.01"
                    class="form-input"
                  />
                </div>
                <div class="form-group">
                  <label for="sim-line">Dòng máy / Line</label>
                  <select id="sim-line" v-model="simForm.machine_line" class="form-select">
                    <option value="L1">Line L1 (1:5)</option>
                    <option value="L2">Line L2 (1:4)</option>
                    <option value="L3">Line L3 (1:5)</option>
                    <option value="L4">Line L4 (1:5)</option>
                    <option value="T5">Line T5 (1:4)</option>
                    <option value="T6">Line T6 (1:4)</option>
                    <option value="T7">Line T7 (1:5)</option>
                    <option value="T8">Line T8 (1:5)</option>
                  </select>
                </div>
              </div>

              <div class="form-group row-group">
                <label for="sim-white">Nhuộm vải màu trắng (White)</label>
                <input 
                  id="sim-white"
                  v-model="simForm.is_white" 
                  type="checkbox" 
                  class="form-checkbox"
                />
              </div>

              <button id="run-sim-btn" @click="runSimulation" class="sim-btn" :disabled="simulating">
                {{ simulating ? 'Đang tính...' : '🧮 Tính toán Thể tích & Trọng lượng' }}
              </button>

              <!-- Sim Results -->
              <div v-if="simResult" class="sim-result-card">
                <h4>📊 Kết Quả Định Lượng:</h4>
                <div class="result-meta-grid">
                  <div class="meta-box">
                    <span class="label">Mã công đoạn:</span>
                    <span class="value">{{ simResult.process_code }}</span>
                  </div>
                  <div class="meta-box">
                    <span class="label">Tổng thể tích nước:</span>
                    <span class="value text-glow-blue">{{ simResult.water_volume }} L</span>
                  </div>
                </div>

                <div class="materials-result-list">
                  <h5>🧪 Lượng cân chi tiết nguyên liệu:</h5>
                  <table class="result-table">
                    <thead>
                      <tr>
                        <th>Mã vật tư</th>
                        <th class="number-cell">Nồng độ (g/l)</th>
                        <th class="number-cell">Trọng lượng cân (g)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="mat in simResult.materials" :key="mat.material_code">
                        <td class="bold-text highlight-code">{{ mat.material_code }}</td>
                        <td class="number-cell">{{ mat.concentration }} g/l</td>
                        <td class="number-cell bold-text text-glow">{{ mat.target_weight }} g</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();

// Navigation Mode
const activeMode = ref('list');

// List Mode State
const recipes = ref<any[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

// Create Mode State
const newRecipe = ref({
  color_code: '',
  product_code: '',
  description: '',
  tension_value: null as number | null,
  water_ratio_override: null as number | null,
  materials: [
    { material_code: '', concentration: 0.0, process_code: 'P', tank_number: null as number | null }
  ]
});
const saving = ref(false);
const message = ref('');

// Simulator State
const simForm = ref({
  weight: 6.5,
  machine_line: 'L2',
  is_white: false
});
const simulating = ref(false);
const simResult = ref<any>(null);

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};

const fetchRecipes = async () => {
  try {
    const token = authStore.token;
    const response = await axios.get('/api/recipes', {
      headers: { Authorization: `Bearer ${token}` },
      params: {
        page: currentPage.value,
        search: searchQuery.value || undefined
      }
    });
    recipes.value = response.data.data;
    currentPage.value = response.data.current_page;
    totalPages.value = response.data.last_page;
  } catch (error) {
    console.error('Fetch recipes error:', error);
  }
};

let searchTimeout: any = null;
const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchRecipes();
  }, 300);
};

const changePage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchRecipes();
  }
};

// Create Mode Actions
const addMaterialRow = () => {
  newRecipe.value.materials.push({
    material_code: '',
    concentration: 0.0,
    process_code: 'P',
    tank_number: null
  });
};

const removeMaterialRow = (idx: number) => {
  newRecipe.value.materials.splice(idx, 1);
};

const saveRecipe = async () => {
  saving.value = true;
  message.value = '';
  try {
    const token = authStore.token;
    await axios.post('/api/recipes', newRecipe.value, {
      headers: { Authorization: `Bearer ${token}` }
    });
    message.value = 'Lưu công thức thành công!';
    // Reset form
    newRecipe.value = {
      color_code: '',
      product_code: '',
      description: '',
      tension_value: null,
      water_ratio_override: null,
      materials: [
        { material_code: '', concentration: 0.0, process_code: 'P', tank_number: null }
      ]
    };
    currentPage.value = 1;
    fetchRecipes();
    activeMode.value = 'list';
  } catch (error) {
    console.error('Save recipe error:', error);
    alert('Lỗi khi lưu công thức! Vui lòng kiểm tra lại mã nguyên liệu.');
  } finally {
    saving.value = false;
  }
};

// Open calculator pre-filled with recipe
const openCalculator = (recipe: any) => {
  activeMode.value = 'create'; // Switch to create/sim view
  simForm.value.is_white = recipe.latest_version?.parameters?.water_ratio_override === 1.1;
  
  // Fill in new recipe fields to show in form
  newRecipe.value.color_code = recipe.color_code;
  newRecipe.value.product_code = recipe.product_code;
  newRecipe.value.description = recipe.description || '';
  newRecipe.value.tension_value = recipe.latest_version?.parameters?.tension_value;
  newRecipe.value.water_ratio_override = recipe.latest_version?.parameters?.water_ratio_override;
  newRecipe.value.materials = recipe.latest_version?.materials.map((m: any) => ({
    material_code: m.material_code,
    concentration: m.concentration,
    process_code: m.process_code,
    tank_number: m.tank_number
  }));
  
  // Trigger simulation automatically
  runSimulation();
};

// Simulation
const runSimulation = async () => {
  simulating.value = true;
  simResult.value = null;
  try {
    const token = authStore.token;
    // Build parameters for preview calculation API
    const payload = {
      weight: simForm.value.weight,
      machine_line: simForm.value.machine_line,
      color_code: newRecipe.value.color_code || 'A+110293', // Fallback preview
      is_white: simForm.value.is_white,
      water_ratio_override: newRecipe.value.water_ratio_override || undefined,
      materials: newRecipe.value.materials.map(m => ({
        material_code: m.material_code || 'Y1008A', // Fallback preview
        concentration: m.concentration
      }))
    };
    
    const response = await axios.post('/api/calculations/preview', payload, {
      headers: { Authorization: `Bearer ${token}` }
    });
    
    simResult.value = response.data.data;
  } catch (error) {
    console.error('Simulation error:', error);
  } finally {
    simulating.value = false;
  }
};

onMounted(() => {
  fetchRecipes();
});
</script>

<style scoped>
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
.mode-toggle-header {
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}
.mode-buttons {
  display: flex;
  gap: 1rem;
}
.mode-btn {
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
}
.mode-btn.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 0 12px var(--primary-shadow);
}
.search-box {
  width: 250px;
}

/* Recipes List Grid */
.recipes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}
.recipe-card {
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 4px 20px var(--shadow-card);
  transition: transform 0.2s ease, border-color 0.2s ease;
}
.recipe-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary);
}
.recipe-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: 0.8rem;
}
.recipe-color-tag {
  background: hsla(35, 100%, 65%, 0.15);
  color: hsl(35, 100%, 65%);
  border: 1px solid hsla(35, 100%, 65%, 0.3);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
  font-size: 0.9rem;
  display: inline-block;
  margin-bottom: 4px;
}
.version-tag {
  background: var(--bg-tag);
  color: var(--text-subtitle);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.8rem;
}
.recipe-desc {
  font-size: 0.9rem;
  color: var(--text-subtitle);
  margin-bottom: 0.8rem;
}
.recipe-meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: var(--text-label);
  background: hsla(224, 20%, 10%, 0.3);
  padding: 6px 12px;
  border-radius: 6px;
  margin-bottom: 1rem;
}
.materials-list-summary h5 {
  font-size: 0.9rem;
  color: var(--text-title);
  margin-bottom: 0.5rem;
}
.materials-list-summary ul {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.materials-list-summary li {
  font-size: 0.85rem;
  color: var(--text-body);
}
.mat-code {
  color: hsl(200, 100%, 65%);
  font-weight: 600;
}
.mat-conc {
  color: var(--text-subtitle);
  margin-left: 4px;
}
.calc-action-btn {
  width: 100%;
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  margin-top: 1rem;
  transition: all 0.2s ease;
}
.calc-action-btn:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 0 10px var(--primary-shadow);
}
.empty-card {
  grid-column: 1 / -1;
  text-align: center;
  color: var(--text-subtitle);
  padding: 3rem;
  background: var(--bg-card);
  border: 1px dashed var(--border-card);
  border-radius: 12px;
}

/* Create Form Specific */
.two-col-form-row {
  display: flex;
  gap: 1rem;
}
.two-col-form-row .form-group {
  flex: 1;
}
.materials-form-section {
  border-top: 1px solid var(--border-divider);
  padding-top: 1rem;
  margin-top: 1rem;
  margin-bottom: 1.5rem;
}
.materials-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.add-row-btn {
  background: hsla(250, 100%, 65%, 0.15);
  color: hsl(250, 100%, 75%);
  border: 1px solid hsla(250, 100%, 65%, 0.4);
  padding: 4px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.add-row-btn:hover {
  background: var(--primary);
  color: #fff;
}
.material-row {
  display: flex;
  gap: 0.8rem;
  align-items: flex-end;
  margin-bottom: 0.8rem;
}
.width-90 {
  width: 90px;
}
.width-60 {
  width: 60px;
}
.flex-1 {
  flex: 1;
}
.remove-row-btn {
  background: transparent;
  border: none;
  color: var(--danger-text);
  font-size: 1.2rem;
  cursor: pointer;
  padding-bottom: 6px;
}
.remove-row-btn:hover {
  color: var(--danger-text-hover);
}

/* Calculator specific */
.sim-btn {
  background: hsl(250, 100%, 65%);
  border: 1px solid hsl(250, 100%, 65%);
  color: #fff;
  padding: 10px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  width: 100%;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px var(--primary-shadow);
}
.sim-btn:hover:not(:disabled) {
  background: var(--primary-hover);
  border-color: var(--primary-hover);
  box-shadow: 0 4px 16px var(--primary-shadow-hover);
}
.sim-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.sim-result-card {
  background: hsla(224, 20%, 10%, 0.4);
  border: 1px solid var(--border-card);
  border-radius: 10px;
  padding: 1.2rem;
  margin-top: 1.5rem;
}
.result-meta-grid {
  display: flex;
  gap: 1.5rem;
  margin: 1rem 0;
}
.meta-box {
  flex: 1;
  background: hsla(224, 25%, 16%, 0.6);
  border: 1px solid var(--border-divider);
  padding: 10px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}
.meta-box .label {
  font-size: 0.8rem;
  color: var(--text-subtitle);
}
.meta-box .value {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text-title);
  margin-top: 4px;
}
.text-glow-blue {
  color: hsl(200, 100%, 65%) !important;
  text-shadow: 0 0 10px rgba(0, 191, 255, 0.4);
}
.materials-result-list h5 {
  font-size: 0.9rem;
  color: var(--text-title);
  margin-bottom: 0.6rem;
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: 4px;
}
.result-table {
  width: 100%;
  border-collapse: collapse;
}
.result-table th, .result-table td {
  padding: 6px;
  font-size: 0.85rem;
  text-align: left;
}
.result-table th {
  color: var(--text-subtitle);
}
.result-table td {
  color: var(--text-body);
}
.result-table tr:not(:last-child) {
  border-bottom: 1px solid var(--border-divider);
}
.highlight-code {
  color: hsl(35, 100%, 65%);
}
.text-glow {
  color: hsl(142, 70%, 50%);
  text-shadow: 0 0 8px rgba(34, 197, 94, 0.3);
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
</style>
