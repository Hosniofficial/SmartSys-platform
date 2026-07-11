/**
 * Frontend Logger Service
 * Environment-aware logging for Vue.js application
 */

// Get environment variables
const APP_ENV = import.meta.env.VITE_APP_ENV || 'production';
const LOG_LEVEL = import.meta.env.VITE_LOG_LEVEL || (APP_ENV === 'production' ? 'error' : 'debug');

// Log levels with priority
const levels = {
  debug: 0,
  info: 1,
  warning: 2,
  error: 3,
  critical: 4
};

/**
 * Check if logging should be enabled for the given level
 */
const shouldLog = (level) => {
  if (!levels[level] || !levels[LOG_LEVEL]) {
    return false;
  }
  return levels[level] >= levels[LOG_LEVEL];
};

/**
 * Format log message with timestamp and structure
 */
const formatMessage = (level, message, data = {}) => {
  const logEntry = {
    timestamp: new Date().toISOString(),
    level: level.toUpperCase(),
    message,
    data,
    env: APP_ENV,
    url: window.location.href,
    userAgent: navigator.userAgent
  };

  return JSON.stringify(logEntry, null, 2);
};

/**
 * Core logging function
 */
const log = (level, message, data = {}) => {
  if (!shouldLog(level)) {
    return;
  }

  const formattedMessage = formatMessage(level, message, data);
  const prefix = `[${level.toUpperCase()}]`;

  switch (level) {
    case 'debug':
      console.log(prefix, message, data);
      break;
    case 'info':
      console.info(prefix, message, data);
      break;
    case 'warning':
      console.warn(prefix, message, data);
      break;
    case 'error':
    case 'critical':
      console.error(prefix, message, data);
      break;
    default:
      console.log(prefix, message, data);
  }

  // Also log to localStorage for debugging in development
  if (APP_ENV !== 'production') {
    try {
      const logs = JSON.parse(localStorage.getItem('debug_logs') || '[]');
      logs.push({
        timestamp: Date.now(),
        level,
        message,
        data
      });
      
      // Keep only last 100 logs
      if (logs.length > 100) {
        logs.shift();
      }
      
      localStorage.setItem('debug_logs', JSON.stringify(logs));
    } catch (e) {
      // Ignore localStorage errors
    }
  }
};

/**
 * Logger service object
 */
const logger = {
  // Basic logging methods
  debug: (message, data = {}) => log('debug', message, data),
  info: (message, data = {}) => log('info', message, data),
  warning: (message, data = {}) => log('warning', message, data),
  error: (message, data = {}) => log('error', message, data),
  critical: (message, data = {}) => log('critical', message, data),

  // Specialized logging methods
  auth: (action, userId = null, data = {}) => {
    logger.info(`Auth: ${action}`, {
      action,
      userId,
      ...data
    });
  },

  api: (method, endpoint, status = null, data = {}) => {
    logger.info(`API: ${method} ${endpoint}`, {
      method,
      endpoint,
      status,
      ...data
    });
  },

  ui: (component, action, data = {}) => {
    logger.debug(`UI: ${component} ${action}`, {
      component,
      action,
      ...data
    });
  },

  performance: (operation, duration, data = {}) => {
    if (APP_ENV !== 'production') {
      logger.debug(`Performance: ${operation}`, {
        operation,
        duration,
        ...data
      });
    }
  },

  business: (event, data = {}) => {
    logger.info(`Business: ${event}`, data);
  },

  // Utility methods
  group: (label, callback) => {
    if (shouldLog('debug')) {
      console.group(label);
      callback();
      console.groupEnd();
    }
  },

  table: (data, label = 'Table') => {
    if (shouldLog('debug')) {
      console.table(data);
    }
  },

  // Clear localStorage logs
  clearLogs: () => {
    try {
      localStorage.removeItem('debug_logs');
    } catch (e) {
      // Ignore errors
    }
  },

  // Get localStorage logs
  getLogs: () => {
    try {
      return JSON.parse(localStorage.getItem('debug_logs') || '[]');
    } catch (e) {
      return [];
    }
  },

  // Export logs for debugging
  exportLogs: () => {
    const logs = logger.getLogs();
    const blob = new Blob([JSON.stringify(logs, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `debug-logs-${getLocalDateISO()}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }
};

// Global error handler
window.addEventListener('error', (event) => {
  logger.critical('Global JavaScript Error', {
    message: event.message,
    filename: event.filename,
    lineno: event.lineno,
    colno: event.colno,
    error: event.error?.stack
  });
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', (event) => {
  logger.critical('Unhandled Promise Rejection', {
    reason: event.reason,
    promise: event.promise
  });
});

export default logger;
