// Vitest global setup — keeps console output predictable in test runs.
import { afterEach, vi } from 'vitest';

afterEach(() => {
  vi.restoreAllMocks();
});
