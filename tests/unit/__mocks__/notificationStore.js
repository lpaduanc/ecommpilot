import { vi } from 'vitest';
import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notification', () => {
  const success = vi.fn();
  const error = vi.fn();
  const info = vi.fn();
  const warning = vi.fn();

  return {
    success,
    error,
    info,
    warning,
  };
});
