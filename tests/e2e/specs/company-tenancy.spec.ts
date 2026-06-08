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

  test('My suppliers shows connected suppliers and Discover lists the rest', async ({ page }) => {
    await login(page, 'group@cellaros.test');
    await page.goto('/suppliers');

    // The group is connected to these.
    await expect(page.getByText('Italian Fine Wines')).toBeVisible();
    await expect(page.getByText('New World Selections')).toBeVisible();
    // Not connected to Bordeaux (it's discoverable), so it isn't in My suppliers.
    await expect(page.getByText('Bordeaux Imports')).toHaveCount(0);

    // Discover lists the unconnected public suppliers.
    await page.getByRole('button', { name: 'Discover' }).click();
    await expect(page.getByText('Bordeaux Imports')).toBeVisible();
  });

  test('the catalogue is scoped to connected suppliers', async ({ page }) => {
    // The Free demo company has no supplier connections yet.
    await login(page, 'free@cellaros.test');
    await page.goto('/catalogue');

    await expect(page.getByText('No suppliers connected yet')).toBeVisible();
    // None of the shared catalogue wines show without a connection.
    await expect(page.getByText('Barolo Riserva')).toHaveCount(0);
  });
});
