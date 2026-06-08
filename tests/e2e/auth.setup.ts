import { test as setup, expect } from '@playwright/test';

const authFile = 'tests/e2e/.auth/user.json';

/**
 * Logs in as the seeded demo user once and persists the session so the
 * `chromium` project can reuse it (see playwright.config.ts storageState).
 */
setup('authenticate', async ({ page }) => {
  await page.goto('/login');

  await page.getByLabel('Email').fill(process.env.E2E_USER_EMAIL || 'demo@cellaros.test');
  await page.getByLabel('Password', { exact: true }).fill(process.env.E2E_USER_PASSWORD || 'password');
  await page.getByRole('button', { name: /log in/i }).click();

  await expect(page).toHaveURL(/dashboard/);

  await page.context().storageState({ path: authFile });
});
