import { test as setup, expect } from '@playwright/test';

const authFile = 'tests/e2e/.auth/user.json';

/**
 * Authenticates once and persists the session storage state so the `chromium`
 * project can reuse it for authenticated specs (see playwright.config.ts
 * `dependencies: ['setup']`).
 *
 * Update the credentials/selectors once the login flow and a seeded test user
 * exist.
 */
setup('authenticate', async ({ page }) => {
  await page.goto('/login');

  await page.getByLabel('Email').fill(process.env.E2E_USER_EMAIL || 'test@cellaros.test');
  await page.getByLabel('Password').fill(process.env.E2E_USER_PASSWORD || 'password');
  await page.getByRole('button', { name: /log in/i }).click();

  await expect(page).toHaveURL(/dashboard/);

  await page.context().storageState({ path: authFile });
});
