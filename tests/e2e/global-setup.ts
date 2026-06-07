import type { FullConfig } from '@playwright/test';

/**
 * Global setup runs once before the whole E2E suite.
 *
 * Use it to seed the database into a known state for browser tests, e.g. by
 * hitting an artisan command or a dedicated test-only endpoint. Kept as a
 * no-op for now — wire in DB seeding once the test fixtures exist.
 */
async function globalSetup(_config: FullConfig): Promise<void> {
  // e.g. await exec('php artisan migrate:fresh --seed --env=testing');
}

export default globalSetup;
