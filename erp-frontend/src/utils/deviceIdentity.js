/**
 * POS Device Identity — Single Source of Truth
 * Multi-terminal safe (POS / cashier systems)
 */

const DEVICE_ID_KEY   = 'pos_device_id';
const DEVICE_NAME_KEY = 'pos_device_name';

/**
 * Generate stable random ID
 */
function generateDeviceId() {
  return (
    'dev-' +
    Math.random().toString(36).slice(2, 10) +
    '-' +
    Date.now().toString(36).slice(-6)
  );
}

/**
 * Get fallback device label from environment
 */
function getSystemFallbackName() {
  if (typeof navigator === 'undefined') return 'POS Device';

  const platform = navigator.platform || '';
  const ua = navigator.userAgent || '';

  const base =
    platform ||
    (ua.includes('Windows') && 'Windows Device') ||
    (ua.includes('Android') && 'Android Device') ||
    (ua.includes('iPhone') && 'iPhone Device') ||
    'POS Device';

  return base;
}

/**
 * Stable device identity (MAIN API)
 */
export function getDeviceIdentity() {
  let id = localStorage.getItem(DEVICE_ID_KEY);

  if (!id) {
    id = generateDeviceId();
    try {
      localStorage.setItem(DEVICE_ID_KEY, id);
    } catch {}
  }

  const custom = (localStorage.getItem(DEVICE_NAME_KEY) || '').trim();

  const baseName = custom || getSystemFallbackName();

  const name = baseName.slice(0, 64);

  return {
    device_id: id,
    device_name: name,
  };
}

/**
 * Update device name (for cashier/admin)
 */
export function setDeviceName(name) {
  try {
    localStorage.setItem(
      DEVICE_NAME_KEY,
      (name || '').trim().slice(0, 64)
    );
  } catch {}
}

/**
 * Get best label for session device display
 */
export function sessionDeviceLabel(session) {
  const local = getDeviceIdentity();

  if (!session) return local.device_name;

  if (session.device_name) return session.device_name;

  if (session.device_id) return `Device ${session.device_id}`;

  return local.device_name;
}

/**
 * Optional: check if session belongs to this device
 */
export function isCurrentDevice(session) {
  if (!session?.device_id) return false;

  const local = getDeviceIdentity();

  return session.device_id === local.device_id;
}