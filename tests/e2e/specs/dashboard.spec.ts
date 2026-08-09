import { test, expect } from '@playwright/test';

test.describe('Authenticated dashboard', () => {
  test('shows the dashboard with KPI cards', async ({ page }) => {
    await page.goto('/dashboard');

    // The masthead is "<First name>’s cellar", not a generic welcome.
    await expect(page.getByRole('heading', { name: /cellar$/i })).toBeVisible();
    await expect(page.getByText('In-stock value')).toBeVisible();
    await expect(page.getByText('wines to browse')).toBeVisible();
  });

  test('navigates to the catalogue from the sidebar', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('link', { name: 'Catalogue' }).click();

    await expect(page).toHaveURL(/catalogue/);
  });
});
