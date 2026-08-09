import { test, expect } from '@playwright/test';

test.describe('Catalogue', () => {
  // The catalogue renders a stacked card list for small screens and a table for
  // large ones; the cards come first in the DOM. Scope assertions to the table
  // so `.first()` can't match a hidden mobile card.
  const table = (page) => page.locator('table');

  test('lists seeded wines and filters by search', async ({ page }) => {
    await page.goto('/catalogue');

    // Two wines the demo seeder always creates by name, so the assertion
    // doesn't depend on which real wines it sampled for the rest of the list.
    await page.getByPlaceholder('Search wine or producer…').fill('Crémant de Loire');
    await expect(table(page).getByText('Crémant de Loire Rosé Brut').first()).toBeVisible();

    await page.getByPlaceholder('Search wine or producer…').fill('Tawny Port');
    await expect(table(page).getByText('Tawny Port 10 Year Old').first()).toBeVisible();
    await expect(table(page).getByText('Crémant de Loire')).toHaveCount(0);
  });

  test('adds a chosen quantity to the order basket', async ({ page }) => {
    // wire:confirm uses a native dialog — accept it automatically.
    page.on('dialog', (dialog) => dialog.accept());

    await page.goto('/catalogue');

    // The basket persists in the session, so start from empty to make the
    // count assertion below mean something.
    const basketButton = page.getByRole('button', { name: /Basket/ });
    await basketButton.click();
    const clear = page.getByRole('button', { name: 'Clear', exact: true });
    if (await clear.isVisible().catch(() => false)) {
      await clear.click();
    }
    // An empty basket has no buttons at all, so close on Escape either way.
    await page.keyboard.press('Escape');

    // "Add" opens a panel to choose how many, rather than adding one silently.
    await table(page).getByRole('button', { name: 'Add to basket' }).first().click();

    const panel = page.getByRole('dialog').filter({ hasText: 'How many' });
    await expect(panel).toBeVisible();

    // Each step is a Livewire round trip; wait for it to land before the next.
    await panel.getByRole('button', { name: 'One more' }).click();
    await expect(panel.getByRole('spinbutton', { name: 'Quantity' })).toHaveValue('2');

    await panel.getByRole('button', { name: 'Add to basket' }).click();

    // The badge counts distinct WINES, not bottles, so one line either way.
    await expect(basketButton).toContainText('1');

    // The chosen quantity is what landed on that line.
    await basketButton.click();
    await expect(page.getByText('Order basket')).toBeVisible();
    await expect(page.getByText('Total (1 wine)')).toBeVisible();
    // Bottles for a bottle-sold wine, cases for a case-sold one — either way
    // the line carries the two the buyer asked for.
    await expect(page.getByRole('spinbutton', { name: /^(Bottles|Cases) of / })).toHaveValue('2');
  });
});
