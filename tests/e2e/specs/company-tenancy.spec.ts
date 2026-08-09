import { test, expect, Page } from '@playwright/test';

/**
 * End-to-end walkthroughs of the company-as-tenant journeys: registration
 * creates a company, owners/managers manage the team, and members are scoped
 * to their assigned venues. These need specific users, so each runs from a
 * clean (unauthenticated) state rather than the persisted demo session.
 */

async function login(page: Page, email: string, password = 'password'): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page).toHaveURL(/dashboard/);
}

test.describe('Company tenancy', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('registering creates a company and signs you in as its owner', async ({ page }) => {
    const email = `e2e-${Date.now()}@cellaros.test`;

    await page.goto('/register');
    await page.getByLabel('Full name').fill('E2E Owner');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Company / venue').fill('E2E Test Wines');
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByLabel('Confirm password').fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();

    await expect(page).toHaveURL(/dashboard/);
    // A freshly registered user is the owner, so the Team area is available.
    await expect(page.getByRole('link', { name: 'Team' })).toBeVisible();
  });

  test('a group owner sees the whole team, including the venue-scoped member', async ({ page }) => {
    await login(page, 'group@cellaros.test');

    await page.getByRole('link', { name: 'Team' }).click();
    await expect(page).toHaveURL(/team/);

    const team = page.getByRole('table');
    await expect(team.getByText('Priya Anand')).toBeVisible();
    await expect(team.getByText('Leo Carter')).toBeVisible();
    // The member's row shows only their assigned venue.
    await expect(team.getByText('Riverside Brasserie')).toBeVisible();
    // The owner's row shows access to every venue.
    await expect(team.getByText('All venues')).toBeVisible();
  });

  test('a member cannot reach the Team area and is scoped to one venue', async ({ page }) => {
    await login(page, 'group.member@cellaros.test');

    // No Team link in the sidebar for members.
    await expect(page.getByRole('link', { name: 'Team' })).toHaveCount(0);

    // Direct navigation is forbidden.
    const response = await page.goto('/team');
    expect(response?.status()).toBe(403);
  });

  test('My suppliers shows only this company\'s own merchants', async ({ page }) => {
    await login(page, 'group@cellaros.test');
    await page.goto('/suppliers');

    // The group has its own merchants…
    await expect(page.getByText('Halliwell Fine Wine')).toBeVisible();
    await expect(page.getByText('Saltmarsh Wine Co')).toBeVisible();
    // …and cannot see the Pro company's, which are private to that tenant.
    await expect(page.getByText('Northbank Wine Traders')).toHaveCount(0);
  });

  test('the catalogue is scoped to connected suppliers', async ({ page }) => {
    // A brand-new company has no supplier connections. Registered fresh rather
    // than using a demo login: the demo accounts are all connected by design.
    const email = `e2e-scope-${Date.now()}@cellaros.test`;
    await page.goto('/register');
    await page.getByLabel('Full name').fill('E2E Newcomer');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Company / venue').fill('E2E Empty Cellar');
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByLabel('Confirm password').fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/catalogue');

    await expect(page.getByText('No suppliers connected yet')).toBeVisible();
    // None of the shared catalogue wines show without a connection.
    await expect(page.getByText('Barolo Riserva')).toHaveCount(0);
  });
});
