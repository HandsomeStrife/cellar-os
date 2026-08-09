import { test, expect } from '@playwright/test';

/**
 * The parse → review → approve journey, using the seeded Borough Wine Co
 * document for the Pro demo company (the LLM step itself is exercised by the
 * Pest suite; here we drive the review UI against seeded proposals).
 */
test.describe('Supplier document parsing', () => {
  test('a buyer reviews parsed wines and approves them into the catalogue', async ({ page }) => {
    // wire:confirm uses a native dialog — accept it automatically.
    page.on('dialog', (dialog) => dialog.accept());

    await page.goto('/suppliers');

    // Row actions live in a dropdown now (3+ actions collapse by house style).
    await page.getByRole('button', { name: 'Actions for Borough Wine Co' }).click();
    await page.getByRole('menuitem', { name: 'Price lists & documents' }).click();
    await expect(page).toHaveURL(/\/documents$/);

    await page.getByRole('link', { name: 'Review' }).first().click();
    await expect(page).toHaveURL(/\/review$/);

    // The proposed wines are listed.
    await expect(page.getByText('Borough Reserve Claret')).toBeVisible();

    await page.getByRole('button', { name: 'Approve all proposed' }).click();
    await expect(page.getByText(/added to your catalogue/i)).toBeVisible();

    // The approved wine now shows in the (connection-scoped) catalogue.
    await page.goto('/catalogue');
    await page.getByPlaceholder('Search wine or producer').fill('Borough Reserve Claret');
    // Scoped to the table: the stacked mobile card list renders the same name.
    await expect(page.locator('table').getByText('Borough Reserve Claret').first()).toBeVisible();
  });
});
