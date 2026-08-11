import { expect, test, type Page } from '@playwright/test';

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'password';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(adminEmail);
    await page.getByLabel('Password').fill(adminPassword);
    await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Enter workspace' }).click()]);
}

test.describe('staff core journeys', () => {
    test('redirects guests away from the partner commercial workspace', async ({ page }) => {
        await page.goto('/partners/commercial');

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Sign in to your workspace' })).toBeVisible();
    });

    test('renders the staff password recovery screens', async ({ page }) => {
        await page.goto('/forgot-password');
        await expect(page.getByRole('heading', { name: 'Reset your password' })).toBeVisible();

        await page.goto('/reset-password/example-token?email=admin%40example.com');
        await expect(page.getByRole('heading', { name: 'Choose a new password' })).toBeVisible();
    });

    test('lets the owner sign in and open the partner commercial workspace', async ({ page }) => {
        await signIn(page);

        await expect(page.getByRole('heading', { name: 'Your operations at a glance.' })).toBeVisible();
        await page.goto('/partners/commercial');
        await expect(page).toHaveURL(/\/partners\/commercial/);
        await expect(page.getByRole('heading', { name: 'Prices and settlements' })).toBeVisible();
    });

    test('keeps the customer queue and settings reachable for the owner', async ({ page }) => {
        await signIn(page);

        await page.goto('/customers');
        await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible();
        await page.goto('/settings/general');
        await expect(page.getByRole('heading', { name: 'Workspace settings' })).toBeVisible();
    });

    test('renders the workspace in right-to-left mode when configured', async ({ page }) => {
        await signIn(page);
        await page.goto('/settings/general');
        await page.goto('/security/reauthenticate');
        await page.getByLabel('Password').fill(adminPassword);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Confirm' }).click()]);

        await page.goto('/settings/general');
        await page.getByLabel('Locale').selectOption('ar');
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('[dir="rtl"]')).toBeVisible();

        await page.goto('/settings/general');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('[dir="ltr"]')).toBeVisible();
    });
});
