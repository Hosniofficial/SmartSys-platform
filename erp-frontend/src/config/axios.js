// src/config/apiClient.js
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import logger from '@/utils/logger';
import deviceFingerprint from '@/utils/deviceFingerprint';

// Safe JSON parse for localStorage values
const safeParseUser = (raw) => {
  try { return raw ? JSON.parse(raw) : null; } catch { return null; }
};

// Auto-detect Base URL based on environment
function getBaseURL() {
  // In development: use full URL to backend server
  if (import.meta.env.MODE === 'development') {
    // Use full URL to backend server for direct CORS communication
    // Development backend runs at http://localhost:8000 with no /api prefix
    const apiTarget = import.meta.env.VITE_API_TARGET || 'http://localhost:8000';
    const apiBasePath = import.meta.env.VITE_API_BASE_URL || '';
    return apiTarget + apiBasePath;
  }
  
  // In production: use relative path
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL;
  }
  
  // Default for production: path from same origin
  return '/smartsys/api/v1';
}

const apiClient = axios.create({
  baseURL: getBaseURL(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  // withCredentials: true is required so the browser sends the HttpOnly
  // refresh_token cookie on cross-origin requests to /auth/refresh,
  // /auth/logout, and /auth/logout-all-devices.
  withCredentials: true,
  timeout: 10000,
});

// Register BASE_URL for development and testing
if (import.meta.env.MODE === 'development') {
  console.log(`[API Config] Mode: ${import.meta.env.MODE}, Base URL: ${apiClient.defaults.baseURL}`);
}
apiClient.interceptors.request.use(
  async (config) => {
    // Proactive token refresh
    // Skip on: refresh endpoint, explicit skipTokenRefresh flag
    try {
      const skipTokenRefresh = config.meta?.skipTokenRefresh || 
                              config.url?.includes('/auth/refresh') || 
                              config.url?.includes('/auth/verify-email');
      
      if (!skipTokenRefresh) {
        const authStore = useAuthStore();
        await authStore.checkAndRefreshToken();
      }
    } catch (e) {
      console.warn('Token check failed:', e);
    }

    // Global loader
    try {
      const ui = useUiStore();
      if (!config.meta?.skipLoader) ui.showLoader();
    } catch (_) { /* store not ready */ }

    // Attach access token from memory (never from localStorage)
    try {
      const authStore = useAuthStore();
      if (authStore.token) {
        config.headers.Authorization = `Bearer ${authStore.token}`;
      }
    } catch (_) { /* store not ready — header may already be set on apiClient.defaults */ }

    // Tenant ID — from store first, localStorage fallback
    let tenantId = null;
    try {
      const authStore = useAuthStore();
      if (authStore.user?.tenant_id) tenantId = authStore.user.tenant_id;
    } catch (_) { /* ignore */ }

    if (!tenantId) {
      const u = safeParseUser(localStorage.getItem('user'));
      tenantId = u?.tenant_id ?? null;
    }

    if (tenantId) {
      config.headers['X-Tenant-Id'] = tenantId;
      if (import.meta.env.MODE === 'development') {
        console.log(`[API] Tenant: ${tenantId}  ${config.method?.toUpperCase()} ${config.url}`);
      }
    }

    // Accept-Language
    let locale = 'ar';
    try {
      const authStore = useAuthStore();
      locale = authStore.user?.locale || authStore.user?.language || 'ar';
    } catch (_) { /* ignore */ }
    if (locale === 'ar') {
      locale = localStorage.getItem('locale') || localStorage.getItem('lang') || 'ar';
    }
    config.headers['Accept-Language'] = locale;

    // Device fingerprint
    try {
      const fingerprint = await deviceFingerprint.getFingerprint();
      if (fingerprint) config.headers['X-Device-Fingerprint'] = fingerprint;
    } catch (error) {
      logger.warn('Failed to get device fingerprint:', error);
    }

    logger.log(`[API] ${config.method?.toUpperCase()} ${config.baseURL}${config.url}`, config.params || '');
    return config;
  },
  (error) => {
    logger.error('[API] Request Error:', error);
    return Promise.reject(error);
  }
);

// Helper function to extract first valid JSON from response
function extractFirstValidJson(response) {
  // If response is already parsed, return it
  if (typeof response !== 'string') {
    return response;
  }

  // Clean response by removing HTML tags and PHP warnings
  let cleanedResponse = response;
  
  // Remove HTML tags and PHP warnings
  cleanedResponse = cleanedResponse.replace(/<[^>]*>/g, ''); // Remove HTML tags
  cleanedResponse = cleanedResponse.replace(/<br \/>/gi, ''); // Remove line breaks
  cleanedResponse = cleanedResponse.replace(/\n\s*\n/g, '\n'); // Remove multiple newlines
  cleanedResponse = cleanedResponse.trim();
  
  // Try direct JSON parsing first (most efficient)
  try {
    const parsed = JSON.parse(cleanedResponse);
    return parsed;
  } catch (e) {
    // If direct parsing fails, try to extract JSON from the response
  }

  // Try to find all JSON objects in the response
  const jsonObjects = [];
  let currentPos = 0;
  
  try {
    while (currentPos < cleanedResponse.length) {
      const startBrace = cleanedResponse.indexOf('{', currentPos);
      if (startBrace === -1) break;
      
      let openBraces = 0;
      let inString = false;
      let escapeNext = false;
      let endPos = startBrace;
      
      for (let i = startBrace; i < cleanedResponse.length; i++) {
        const char = cleanedResponse[i];
        
        if (escapeNext) {
          escapeNext = false;
          continue;
        }
        
        if (char === '\\') {
          escapeNext = true;
          continue;
        }
        
        if (char === '"' && (i === 0 || cleanedResponse[i-1] !== '\\')) {
          inString = !inString;
        }
        
        if (!inString) {
          if (char === '{') {
            openBraces++;
          } else if (char === '}') {
            openBraces--;
            if (openBraces === 0) {
              // Found a complete JSON object
              try {
                const jsonStr = cleanedResponse.substring(startBrace, i + 1);
                const jsonObj = JSON.parse(jsonStr);
                jsonObjects.push(jsonObj);
              } catch (e) {
                // Continue parsing even if one JSON object fails
                if (import.meta.env.MODE === 'development') {
                  logger.debug('Failed to parse JSON object, continuing:', e.message);
                }
              }
              endPos = i + 1;
              break;
            }
          }
        }
      }
      
      currentPos = endPos;
    }
    
    if (jsonObjects.length > 0) {
      // Look for the response that contains complete data (daily_sales, top_products, etc.)
      for (const obj of jsonObjects) {
        if (obj.data && typeof obj.data === 'object') {
          const data = obj.data;
          // If data contains daily_sales or top_products, this is the correct response
          if (data.daily_sales || data.top_products || data.total_sales_amount || data.openingBalance !== undefined) {
            return obj;
          }
        }
        // Also check for error responses
        if (obj.status && obj.message) {
          return obj;
        }
      }
      
      // If no complete data found, return the first valid object
      return jsonObjects[0];
    }
  } catch (e) {
    logger.error('Error in extractFirstValidJson:', e);
    return null;
  }
  
  return null;
}

// Response Interceptor
apiClient.interceptors.response.use(
  (response) => {
    // Global loader end (unless skipped)
    try {
      const ui = useUiStore();
      if (!response.config?.meta || !response.config.meta.skipLoader) ui.hideLoader();
    } catch (_) { /* ignore */ }
    
    logger.log(`[API] ${response.status} ${response.config.method.toUpperCase()} ${response.config.url}`);
    
    // Handle the case where the response data is a string that might contain concatenated JSON
    if (typeof response.data === 'string') {
      try {
        response.data = extractFirstValidJson(response.data);
      } catch (e) {
        logger.error('Failed to process response data:', e);
        throw e;
      }
    }
    
    // Surface server warnings (e.g., journal_entry_skipped) to the user
    try {
      const warnings = response?.data?.warnings;
      if (warnings && warnings.journal_entry_skipped && typeof window.showToast === 'function') {
        const msg = warnings.skipped_count
          ? `تمت العملية مع تحذير: تم تخطي إنشاء ${warnings.skipped_count} قيد/قيود يومية بسبب إعدادات ناقصة أو قيمة صفرية.`
          : 'تمت العملية مع تحذير: تم تخطي إنشاء قيد اليومية بسبب إعدادات ناقصة أو قيمة صفرية.';
        window.showToast(msg, 'warning');
      }
    } catch (_) { /* ignore toast errors */ }

    return response;
  },
  async (error) => {
    const originalRequest = error.config;

    // Ensure loader is hidden on error as well
    try {
      const ui = useUiStore();
      if (!originalRequest?.meta || !originalRequest.meta.skipLoader) ui.hideLoader();
    } catch (_) { /* ignore */ }

    if (error.response) {
      const { status, data } = error.response;

      // 400 Bad Request on refresh = missing refresh token (new user case) — not a redirect-worthy error
      if (status === 400 && (error.config._isRefreshRequest || error.config.url?.includes('/auth/refresh'))) {
        console.log('[API] 400 on refresh — missing token (expected for new users)');
        return Promise.reject(error);
      }

      if (status === 401 && !originalRequest._retry) {
        // Refresh request handling — handle both success and failure
        if (originalRequest._isRefreshRequest || originalRequest.url?.includes('/auth/refresh')) {
          // 401 على /auth/refresh = جلسة منتهية حقاً → redirect
          try {
            const authStore = useAuthStore();
            authStore.clearAuthData();
          } catch (_) { /* ignore */ }
          window.location.replace('/');
          return Promise.reject(error);
        }

        // Login endpoint returning 401 = wrong credentials — don't redirect, let the component handle it
        if (originalRequest.url?.includes('/auth/login')) {
          return Promise.reject(error);
        }

        // Check if this is a new user just after email verification
        const justAutoLoggedIn = typeof sessionStorage !== 'undefined' 
                              && sessionStorage.getItem('justAutoLoggedIn');

        // 🚫 لا تحاول refresh إذا كان المستخدم حديث التحقق من البريد
        // (لا يوجد refresh token cookie بعد)
        if (justAutoLoggedIn) {
          console.log('[API] Skipping refresh for newly verified user — 401 is expected');
          return Promise.reject(error);
        }

        originalRequest._retry = true;

        try {
          const authStore = useAuthStore();
          const refreshed = await authStore.refreshToken();

          if (refreshed && authStore.token) {
            originalRequest.headers.Authorization = `Bearer ${authStore.token}`;
            return apiClient(originalRequest);
          }
        } catch (refreshError) {
          console.error('Token refresh failed:', refreshError);
        }

        // Refresh failed — clear state and redirect to login
        try {
          const authStore = useAuthStore();
          authStore.clearAuthData();
        } catch (_) { /* ignore */ }
        window.location.replace('/');
      }

      // 402 Subscription Required -> Set flag, let router guard handle redirect
      if (status === 402) {
        const msg = data?.message || 'يرجى الترقية لمتابعة استخدام النظام';
        
        // Update auth store to mark subscription as expired
        try {
          const authStore = useAuthStore();
          authStore.setSubscriptionExpired(msg);
        } catch (_) { /* ignore */ }
        
        // Show toast notification (silent on /upgrade page)
        try {
          const router = getRouter();
          const currentPath = router?.currentRoute?.value?.path || window.location.pathname;
          if (!currentPath.includes('/upgrade')) {
            if (typeof window.showToast === 'function') window.showToast(msg, 'warning');
          }
        } catch (_) { /* ignore */ }
        
        // Return error — don't redirect here (router guard will handle it)
        error.message = msg;
        return Promise.reject(error);
      }

      // 403 / 404 / 405 / 5xx
      let errorMessage = data?.message || '';

switch (status) {
  case 403:
    errorMessage ||= 'ليس لديك صلاحية الوصول';
    break;
  case 404:
    errorMessage ||= 'العنصر غير موجود';
    break;
  case 405:
    errorMessage ||= 'طريقة الطلب غير مسموح بها';
    break;
  default:
    if (status >= 500) {
      errorMessage ||= 'خطأ في الخادم';
    }
}

if (!errorMessage) {
  errorMessage = 'حدث خطأ غير متوقع';
}

      // suppress403: skip toast + log for expected permission-denied scenarios
      const suppress403 = status === 403 && originalRequest?.meta?.suppress403;

      if (suppress403) {
        // Silent — caller handles this gracefully (e.g. Setup.vue loading settings)
      } else {
        logger.error(`[API] ${status} ${originalRequest.method.toUpperCase()} ${originalRequest.url}`, data);

        if (typeof window.showToast === 'function') {
          window.showToast(errorMessage, 'error');
        }
      }

      error.message = errorMessage;
    } else if (error.request) {
      logger.error('[API] No response from server:', error.request);
      error.message = 'لا يوجد استجابة من الخادم';
    } else {
      error.message = 'خطأ في إعداد الطلب';
    }

    return Promise.reject(error);
  }
);

export { apiClient };
export default apiClient;
