import { exec } from 'node:child_process';
import { promisify } from 'node:util';
import type { FullConfig } from '@playwright/test';

const run = promisify(exec);

/**
 * Seed the (idempotent) demo dataset into the dev database before the suite,
 * so specs run against a known state (demo@cellaros.test, the demo catalogue).
 * Set E2E_SKIP_SEED=1 to skip (e.g. when seeding is handled elsewhere).
 */
async function globalSetup(_config: FullConfig): Promise<void> {
  if (process.env.E2E_SKIP_SEED === '1') {
    return;
  }

  try {
    // E2E exercises the FICTIONAL demo content (suppliers, portal, parsed-review
    // demo) which production never seeds - DemoSupplierSeeder is the dev/E2E-only
    // superset of the clean default seeder.
    await run('docker exec cellar-os-app php artisan db:seed --class=DemoSupplierSeeder --force');
  } catch (error) {
    console.warn('global-setup: could not seed via docker — ensure the demo data exists.', error);
  }
}

export default globalSetup;
