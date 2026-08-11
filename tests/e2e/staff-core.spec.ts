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

    test('keeps the owner workspace routes free of authorization and not-found failures', async ({ page }) => {
        test.setTimeout(120_000);
        await signIn(page);

        for (const path of [
            '/dashboard',
            '/customers',
            '/services',
            '/plans',
            '/billing/invoices',
            '/billing/payments',
            '/billing/shifts',
            '/billing/exchange-rates',
            '/operations/credentials',
            '/operations/imports',
            '/operations/incidents',
            '/operations/inventory',
            '/operations/ip-pools',
            '/operations/network-commands',
            '/operations/pops',
            '/operations/routers',
            '/operations/sessions',
            '/operations/tickets',
            '/operations/work-orders',
            '/partners/commercial',
            '/reports/finance',
            '/reports/operations',
            '/settings/general',
            '/settings/users',
            '/security/sessions',
        ]) {
            const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 120_000 });

            expect(response?.status(), path).toBe(200);
        }
    });

    test('shows field navigation on a mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await signIn(page);

        await expect(page.getByRole('navigation', { name: 'Field navigation' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Customers' }).last()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Payments' }).last()).toBeVisible();

        await page.context().setOffline(true);
        await expect(page.getByRole('status')).toContainText('Offline.');
        await page.context().setOffline(false);
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
