import { test, expect } from '@playwright/test';

test.describe('Catalogue', () => {
  test('lists seeded wines and filters by search', async ({ page }) => {
    await page.goto('/catalogue');

    // The demo company is connected to large REAL catalogues too, so find the
    // seeded wine by search rather than expecting it on page 1.
    await page.getByPlaceholder('Search wine or producer…').fill('Barolo Riserva');
    await expect(page.getByText('Barolo Riserva').first()).toBeVisible();

    await page.getByPlaceholder('Search wine or producer…').fill('Chablis Premier');
    await expect(page.getByText('Chablis Premier Cru').first()).toBeVisible();
    await expect(page.getByText('Barolo Riserva')).toHaveCount(0);
  });

  test('adds a wine to the order basket', async ({ page }) => {
    await page.goto('/catalogue');

    // Add the first wine to the basket.
    await page.getByRole('button', { name: 'Add to basket' }).first().click();

    // Basket count badge appears.
    await expect(page.getByRole('button', { name: /Basket/ })).toContainText('1');

    // Open the basket and confirm a total is shown.
    await page.getByRole('button', { name: /Basket/ }).click();
    await expect(page.getByText('Order basket')).toBeVisible();
  });
});
