import { defineStore } from 'pinia';
import axios from 'axios';
import { setWorkstation } from '../services/workstation';

interface Workstation {
  id: number;
  code: string;
  name: string;
  type: string;
  location: string | null;
  assigned_scale_device_id: string | null;
  assigned_printer_device_id: string | null;
  default_screen: string | null;
  /**
   * Tên cột THẬT bên backend (`operation_clients.default_route`). `default_screen` ở trên là tên
   * cũ vẫn còn dùng ở luồng kiosk, nên router đọc `default_route` trước rồi mới rơi về
   * `default_screen` — cả hai đều phải có mặt ở đây.
   */
  default_route?: string | null;
  allowed_actions: string[] | null;
  /**
   * Bổ sung 2026-07-18 để vá lỗi: thiếu trường này thì `workstationHasCapability()` (AppLayout)
   * không tìm ra capability nào của phiên kiosk, mọi route có yêu cầu capability đều bị chặn nhầm
   * "trạm không có quyền". Interface trước đây không khai nên TS báo lỗi ngay tại chỗ gán —
   * xoá trường đi cho hết lỗi là dựng lại đúng con bug đó.
   */
  capability_codes?: string[];
}

interface User {
  id: string;
  username: string;
  display_name: string;
  roles: string[];
  workstation?: Workstation | null;
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('access_token') || localStorage.getItem('df_kiosk_session_token') || '',
    user: JSON.parse(localStorage.getItem('user_info') || 'null') as User | null,
    kioskClient: JSON.parse(localStorage.getItem('df_operation_client') || 'null') as any | null,
    loading: false,
    error: null as string | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    // Không nhận `state`: getter này đọc thẳng localStorage, không phụ thuộc state nào.
    isKiosk: () => !!localStorage.getItem('df_kiosk_session_token'),
    hasRole: (state) => (role: string) => state.user?.roles.includes(role) || false,
    isAdmin: (state) => state.user?.roles.includes('ADMIN') || false,
    isOperator: (state) => state.user?.roles.includes('OPERATOR') || false,
  },

  actions: {
    async login(username: string, password: string) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.post('/api/auth/login', {
          username,
          password
        });
        
        const { access_token, user } = response.data;
        
        this.token = access_token;
        this.user = user;
        this.kioskClient = null;

        localStorage.setItem('access_token', access_token);
        localStorage.setItem('user_info', JSON.stringify(user));
        localStorage.removeItem('df_kiosk_session_token');
        localStorage.removeItem('df_operation_client');

        // Configure Axios default header
        axios.defaults.headers.common['Authorization'] = `Bearer ${access_token}`;
        delete axios.defaults.headers.common['X-Kiosk-Session-Token'];

        if (user.workstation) {
          setWorkstation(user.workstation);
        }
        return true;
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Đăng nhập thất bại. Vui lòng kiểm tra lại kết nối.';
        return false;
      } finally {
        this.loading = false;
      }
    },

    setKioskSession(sessionToken: string, clientInfo: any) {
      this.token = sessionToken;
      this.kioskClient = clientInfo;
      this.user = null;

      localStorage.setItem('df_kiosk_session_token', sessionToken);
      localStorage.setItem('df_operation_client', JSON.stringify(clientInfo));
      localStorage.removeItem('access_token');
      localStorage.removeItem('user_info');

      axios.defaults.headers.common['Authorization'] = `Bearer ${sessionToken}`;
      axios.defaults.headers.common['X-Kiosk-Session-Token'] = sessionToken;

      // Adapt operation client to workstation object format for backward compatibility
      const mappedWorkstation: Workstation = {
        id: clientInfo.id,
        code: clientInfo.code,
        name: clientInfo.name,
        type: clientInfo.default_capability || '',
        location: clientInfo.location,
        assigned_scale_device_id: clientInfo.devices?.find((d: any) => d.role === 'PRIMARY_SCALE')?.code || null,
        assigned_printer_device_id: clientInfo.devices?.find((d: any) => d.role === 'PRIMARY_PRINTER')?.code || null,
        default_screen: clientInfo.default_route || null,
        allowed_actions: clientInfo.capabilities?.map((c: any) => {
          // Map capability to actions for legacy check compatibility
          if (c.code === 'WEIGH') return ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'];
          if (c.code === 'PRINT') return ['SCAN_LABEL', 'REPRINT_LABEL'];
          if (c.code === 'SCAN_QR') return ['SCAN_ORDER', 'SCAN_LABEL', 'SCAN_DUAL_VERIFY', 'CONFIRM_TRANSIT', 'CONFIRM_ARRIVAL'];
          return c.code;
        }).flat() || null,
        // BUG 2026-07-18: thiếu trường này khiến workstationHasCapability() (AppLayout.vue)
        // không tìm thấy capability nào trên phiên kiosk -> mọi route có yêu cầu capability
        // đều bị chặn nhầm "trạm không có quyền" dù kiosk thực sự có quyền đó.
        capability_codes: clientInfo.capabilities?.map((c: any) => c.code) || [],
      };

      setWorkstation(mappedWorkstation);
    },

    logout() {
      if (this.token && !this.isKiosk) {
        axios.post('/api/auth/logout')
          .catch(() => {}); // Ignore logout error if token expired
      }
      this.token = '';
      this.user = null;
      this.kioskClient = null;
      localStorage.removeItem('access_token');
      localStorage.removeItem('user_info');
      localStorage.removeItem('df_kiosk_session_token');
      localStorage.removeItem('df_operation_client');
      delete axios.defaults.headers.common['Authorization'];
      delete axios.defaults.headers.common['X-Kiosk-Session-Token'];
      setWorkstation(null);
    },

    initialize() {
      if (this.token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        if (this.isKiosk) {
          axios.defaults.headers.common['X-Kiosk-Session-Token'] = this.token;
          if (this.kioskClient) {
            this.setKioskSession(this.token, this.kioskClient);
          }
        } else {
          if (this.user?.workstation) {
            setWorkstation(this.user.workstation);
          }
        }
      }
    }
  }
});
