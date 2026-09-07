export const API_BASE = 'http://localhost:3000/api/v1';

const SESSION_KEY = 'dailzo-admin-session';

export function getSession() {
  const raw = window.localStorage.getItem(SESSION_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function setSession(session) {
  window.localStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

export function clearSession() {
  window.localStorage.removeItem(SESSION_KEY);
}

export function authHeaders() {
  const session = getSession();
  return session?.accessToken ? { Authorization: `Bearer ${session.accessToken}` } : {};
}
