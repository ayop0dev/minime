/**
 * Safe Storage Utility
 * 
 * Wraps localStorage/sessionStorage access with feature detection
 * and try/catch to prevent uncaught exceptions when storage is blocked
 * (e.g., in incognito mode, iframes with sandbox, or strict browser settings).
 * 
 * @package minime
 */

/**
 * Check if a storage type is available
 */
function isStorageAvailable(type: 'localStorage' | 'sessionStorage'): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    const storage = window[type];
    const testKey = '__minime_storage_test__';
    storage.setItem(testKey, 'test');
    storage.removeItem(testKey);
    return true;
  } catch {
    return false;
  }
}

// Cache availability checks
let localStorageAvailable: boolean | null = null;
let sessionStorageAvailable: boolean | null = null;

/**
 * Get a value from localStorage safely
 */
export function getLocalItem(key: string): string | null {
  if (localStorageAvailable === null) {
    localStorageAvailable = isStorageAvailable('localStorage');
  }

  if (!localStorageAvailable) {
    return null;
  }

  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * Set a value in localStorage safely
 */
export function setLocalItem(key: string, value: string): boolean {
  if (localStorageAvailable === null) {
    localStorageAvailable = isStorageAvailable('localStorage');
  }

  if (!localStorageAvailable) {
    return false;
  }

  try {
    localStorage.setItem(key, value);
    return true;
  } catch {
    return false;
  }
}

/**
 * Remove a value from localStorage safely
 */
export function removeLocalItem(key: string): boolean {
  if (localStorageAvailable === null) {
    localStorageAvailable = isStorageAvailable('localStorage');
  }

  if (!localStorageAvailable) {
    return false;
  }

  try {
    localStorage.removeItem(key);
    return true;
  } catch {
    return false;
  }
}

/**
 * Get a value from sessionStorage safely
 */
export function getSessionItem(key: string): string | null {
  if (sessionStorageAvailable === null) {
    sessionStorageAvailable = isStorageAvailable('sessionStorage');
  }

  if (!sessionStorageAvailable) {
    return null;
  }

  try {
    return sessionStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * Set a value in sessionStorage safely
 */
export function setSessionItem(key: string, value: string): boolean {
  if (sessionStorageAvailable === null) {
    sessionStorageAvailable = isStorageAvailable('sessionStorage');
  }

  if (!sessionStorageAvailable) {
    return false;
  }

  try {
    sessionStorage.setItem(key, value);
    return true;
  } catch {
    return false;
  }
}

/**
 * Remove a value from sessionStorage safely
 */
export function removeSessionItem(key: string): boolean {
  if (sessionStorageAvailable === null) {
    sessionStorageAvailable = isStorageAvailable('sessionStorage');
  }

  if (!sessionStorageAvailable) {
    return false;
  }

  try {
    sessionStorage.removeItem(key);
    return true;
  } catch {
    return false;
  }
}

/**
 * Check if localStorage is available
 */
export function isLocalStorageAvailable(): boolean {
  if (localStorageAvailable === null) {
    localStorageAvailable = isStorageAvailable('localStorage');
  }
  return localStorageAvailable;
}

/**
 * Check if sessionStorage is available
 */
export function isSessionStorageAvailable(): boolean {
  if (sessionStorageAvailable === null) {
    sessionStorageAvailable = isStorageAvailable('sessionStorage');
  }
  return sessionStorageAvailable;
}
