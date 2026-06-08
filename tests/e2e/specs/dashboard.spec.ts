import { test, expect } from '@playwright/test';

test.describe('Authenticated dashboard', () => {
  test('shows the dashboard with KPI cards', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page.getByRole('heading', { name: /welcome/i })).toBeVisible();
    await expect(page.getByText('Wines in catalogue')).toBeVisible();
    await expect(page.getByText('Open orders')).toBeVisible();
  });

  test('navigates to the catalogue from the sidebar', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('link', { name: 'Catalogue' }).click();

    await expect(page).toHaveURL(/catalogue/);
  });

  test('the Pricing page lists the plans', async ({ page }) => {
    await page.goto('/pricing');

    await expect(page.getByRole('heading', { name: 'Starter', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pro', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Group', exact: true })).toBeVisible();
  });
});
