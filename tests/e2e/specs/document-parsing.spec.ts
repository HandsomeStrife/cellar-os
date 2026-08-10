import { test, expect } from '@playwright/test';

/**
 * The parse → review → approve journey, using the seeded Ashgrove Cellars
 * price list for the Pro demo company (the LLM step itself is exercised by the
 * Pest suite; here we drive the review UI against seeded proposals).
 */
test.describe('Supplier document parsing', () => {
  test('a buyer reviews parsed wines and approves them into the catalogue', async ({ page }) => {
    // wire:confirm uses a native dialog — accept it automatically.
    page.on('dialog', (dialog) => dialog.accept());

    await page.goto('/suppliers');

    // Row actions live in a dropdown now (3+ actions collapse by house style).
    await page.getByRole('button', { name: 'Actions for Ashgrove Cellars' }).click();
    await page.getByRole('menuitem', { name: 'Price lists & documents' }).click();
    await expect(page).toHaveURL(/\/documents$/);

    await page.getByRole('link', { name: 'Review' }).first().click();
    await expect(page).toHaveURL(/\/review$/);

    // The proposed wines are listed. Their names come from whatever the demo
    // sampled, so take one from the page rather than hard-coding it — the
    // point is that THIS wine reaches the catalogue.
    const proposedRows = page.locator('table tbody tr');
    await expect(proposedRows.first()).toBeVisible();
    const wineName = (await proposedRows.first().locator('td').first().innerText()).split('\n')[0].trim();
    expect(wineName.length).toBeGreaterThan(2);

    // The type words this merchant uses that CellarOS couldn't place are
    // offered for mapping right here.
    await expect(page.getByText('Skin Contact')).toBeVisible();

    await page.getByRole('button', { name: 'Approve all proposed' }).click();
    await expect(page.getByText(/added to your catalogue/i)).toBeVisible();

    // …and it is now in the (connection-scoped) catalogue.
    await page.goto('/catalogue');
    await page.getByPlaceholder('Search wine or producer…').fill(wineName);
    await expect(page.locator('table').getByText(wineName).first()).toBeVisible();
  });
});
