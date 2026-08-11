<template>
  <div class="login-container">
    <div class="background-glows">
      <div class="glow glow-1"></div>
      <div class="glow glow-2"></div>
    </div>
    
    <div class="login-card">
      <div class="card-header">
        <div class="logo-placeholder">DF</div>
        <h1>{{ $t('login.heading') }}</h1>
        <p class="subtitle">{{ $t('login.subtitle') }}</p>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <div class="input-group">
          <label for="username">{{ $t('login.usernameLabel') }}</label>
          <div class="input-wrapper">
            <span class="icon">👤</span>
            <input
              type="text"
              id="username"
              v-model="username"
              :placeholder="$t('login.usernamePlaceholder')"
              required
              :disabled="authStore.loading"
            />
          </div>
        </div>

        <div class="input-group">
          <label for="password">{{ $t('login.passwordLabel') }}</label>
          <div class="input-wrapper">
            <span class="icon">🔒</span>
            <input
              type="password"
              id="password"
              v-model="password"
              :placeholder="$t('login.passwordPlaceholder')"
              required
              :disabled="authStore.loading"
            />
          </div>
        </div>

        <div v-if="authStore.error" class="error-banner">
          <span class="error-icon">⚠️</span>
          <span class="error-text">{{ authStore.error }}</span>
        </div>

        <button type="submit" class="submit-btn" :disabled="authStore.loading">
          <span v-if="authStore.loading" class="spinner"></span>
          <span v-else>{{ $t('login.submitButton') }}</span>
        </button>
      </form>

      <div class="card-footer">
        <p>{{ $t('login.footerCopyright') }}</p>
        <span class="version">v1.0.0-Beta</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const username = ref('');
const password = ref('');

const handleLogin = async () => {
  const success = await authStore.login(username.value, password.value);
  if (success) {
    // Vào đây từ nút "Đăng nhập" của một trang công khai (NavToggleButton) thì quay lại đúng
    // trang đó. Chỉ nhận đường dẫn nội bộ bắt đầu bằng "/" (không phải "//") — chuỗi tuỳ ý
    // trên URL không được phép trở thành đích điều hướng ra ngoài.
    const redirect = route.query.redirect;
    const target =
      typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
        ? redirect
        : '/';
    router.push(target);
  }
};
</script>

<style scoped>
.login-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  width: 100%;
  background: var(--bg-main);
  overflow: hidden;
  font-family: 'Outfit', 'Inter', sans-serif;
}

/* Background Glowing Orbs */
.background-glows {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1;
}

.glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.15;
  pointer-events: none;
  animation: float 10s ease-in-out infinite alternate;
}

.glow-1 {
  width: 400px;
  height: 400px;
  background: var(--primary-glow);
  top: 10%;
  left: 15%;
}

.glow-2 {
  width: 500px;
  height: 500px;
  background: var(--secondary-glow);
  bottom: 10%;
  right: 15%;
  animation-delay: -5s;
}

@keyframes float {
  0% {
    transform: translate(0, 0) scale(1);
  }
  100% {
    transform: translate(50px, 30px) scale(1.1);
  }
}

/* Glassmorphism Card */
.login-card {
  position: relative;
  width: 100%;
  max-width: 480px;
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  backdrop-filter: blur(16px);
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 20px 40px var(--shadow-card);
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: 30px;
  animation: slide-up 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slide-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card-header {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.logo-placeholder {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, var(--primary), var(--primary-hover));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.8rem;
  font-weight: 800;
  letter-spacing: -1px;
  box-shadow: 0 8px 16px var(--primary-shadow);
}

.card-header h1 {
  font-size: 1.6rem;
  font-weight: 750;
  color: var(--text-title);
  margin-top: 8px;
  letter-spacing: -0.5px;
  line-height: 1.2;
}

.subtitle {
  font-size: 0.9rem;
  color: var(--text-subtitle);
  line-height: 1.4;
  max-width: 320px;
}

/* Form Styles */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.input-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-label);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper input {
  width: 100%;
  padding: 14px 16px 14px 44px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 12px;
  color: var(--text-input);
  font-size: 0.95rem;
  transition: all 0.25s ease;
}

.input-wrapper input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px var(--primary-shadow-focus);
}

.input-wrapper .icon {
  position: absolute;
  left: 14px;
  font-size: 1.1rem;
  color: var(--text-icon);
  pointer-events: none;
}

/* Error Banner */
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--error-bg);
  border: 1px solid var(--error-border);
  padding: 12px 16px;
  border-radius: 10px;
  color: var(--error-text);
  font-size: 0.88rem;
  line-height: 1.4;
  animation: shake 0.4s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-6px); }
  75% { transform: translateX(6px); }
}

.error-icon {
  font-size: 1.1rem;
}

/* Button & Spinner */
.submit-btn {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--primary), var(--primary-hover));
  border: none;
  border-radius: 12px;
  color: white;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 12px var(--primary-shadow);
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px var(--primary-shadow-hover);
}

.submit-btn:active:not(:disabled) {
  transform: translateY(0);
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border-divider);
  padding-top: 20px;
  font-size: 0.78rem;
  color: var(--text-footer);
}

.version {
  font-weight: 600;
  background: var(--bg-tag);
  padding: 2px 8px;
  border-radius: 6px;
}
</style>
