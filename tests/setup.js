import { vi } from 'vitest';
import { config } from '@vue/test-utils';

// Mock do router
const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
  go: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  currentRoute: {
    value: {
      path: '/',
      query: {},
      params: {},
    },
  },
};

const mockRoute = {
  path: '/',
  query: {},
  params: {},
};

// Global mocks
config.global.mocks = {
  $router: mockRouter,
  $route: mockRoute,
};

// Stubs globais para componentes
config.global.stubs = {
  RouterLink: {
    template: '<a><slot /></a>',
  },
};
