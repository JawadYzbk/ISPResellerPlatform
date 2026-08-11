import { expect, test, type BrowserContext, type Page } from '@playwright/test';

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'password';
type BrowserStorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

const seededRoleJourneys = [
    { label: 'tenant owner', email: 'admin@example.com', path: '/partners/commercial', dashboardCta: 'Add customer' },
    {
        label: 'operations manager',
        email: 'operations.manager@example.com',
        path: '/operations/inventory',
        dashboardCta: 'Add customer',
    },
    {
        label: 'billing manager',
        email: 'billing.manager@example.com',
        path: '/billing/invoices',
        dashboardCta: 'Find customers',
    },
    { label: 'cashier', email: 'cashier@example.com', path: '/billing/invoices', dashboardCta: 'Find customers' },
    { label: 'collector', email: 'collector@example.com', path: '/billing/payments', dashboardCta: 'Find customers' },
    {
        label: 'support agent',
        email: 'support.agent@example.com',
        path: '/operations/tickets',
        dashboardCta: 'Find customers',
    },
    {
        label: 'technician',
        email: 'technician@example.com',
        path: '/operations/work-orders',
        dashboardCta: 'Find customers',
    },
    {
        label: 'network administrator',
        email: 'network.admin@example.com',
        path: '/operations/sessions',
        dashboardCta: 'Find customers',
    },
    {
        label: 'reseller owner',
        email: 'reseller.owner@example.com',
        path: '/partners/commercial',
        dashboardCta: 'Add customer',
    },
    { label: 'reseller staff', email: 'reseller.staff@example.com', path: '/customers', dashboardCta: 'Add customer' },
    { label: 'auditor', email: 'auditor@example.com', path: '/reports/finance', dashboardCta: 'Find customers' },
] as const;

let authenticatedState: BrowserStorageState | null = null;

async function signIn(page: Page): Promise<void> {
    if (authenticatedState !== null) {
        await page.context().addCookies(authenticatedState.cookies);
        await page.goto('/dashboard');

        if (page.url().endsWith('/dashboard')) return;

        authenticatedState = null;
    }

    await page.goto('/login');
    await page.getByLabel('Email address').fill(adminEmail);
    await page.getByLabel('Password').fill(adminPassword);
    await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Enter workspace' }).click()]);
    authenticatedState = await page.context().storageState();
}

async function signInAs(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(adminPassword);
    await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Enter workspace' }).click()]);
}

test.describe('staff core journeys', () => {
    test('redirects guests away from the partner commercial workspace', async ({ page }) => {
        await page.goto('/partners/commercial');

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Sign in to your workspace' })).toBeVisible();
    });

    test('renders the customer portal entry and protects the dashboard', async ({ page }) => {
        await page.goto('/portal/northline');
        await expect(page.getByRole('heading', { name: 'Manage your connection.' })).toBeVisible();
        await expect(page.getByLabel('Phone number')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Send code' })).toBeVisible();

        await page.goto('/portal/northline/dashboard');
        await expect(page).toHaveURL(/\/portal\/northline$/);
        await expect(page.getByRole('heading', { name: 'Manage your connection.' })).toBeVisible();
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
        await expect(page.getByRole('heading', { name: 'Prices and settlements' })).toBeVisible({ timeout: 15_000 });
    });

    test('keeps the customer queue and settings reachable for the owner', async ({ page }) => {
        await signIn(page);

        await page.goto('/customers');
        await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible();
        await page.goto('/settings/general');
        await expect(page.getByRole('heading', { name: 'Workspace settings' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Setup signals' })).toBeVisible();
        await expect(page.getByText('Tenant branding', { exact: true })).toBeVisible();
        await expect(page.getByText('Currencies and FX', { exact: true })).toBeVisible();
        await expect(page.getByText('WhatsApp delivery', { exact: true })).toBeVisible();
    });

    test('opens the profile menu and notifications center from the header', async ({ page }) => {
        await signIn(page);
        await expect(page.getByRole('heading', { name: 'Your operations at a glance.' })).toBeVisible();

        await page.getByRole('button', { name: 'Open account menu' }).click();
        await page.getByRole('menuitem', { name: 'Profile' }).click();
        await expect(page).toHaveURL(/\/profile$/);
        await expect(page.getByRole('heading', { name: 'Your profile' })).toBeVisible();

        await page.getByRole('link', { name: 'Open notifications center' }).click();
        await expect(page).toHaveURL(/\/notifications$/);
        await expect(page.getByRole('heading', { name: 'Notifications & attention' })).toBeVisible();
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
            '/settings/whatsapp',
            '/security/sessions',
            '/profile',
            '/notifications',
        ]) {
            const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 120_000 });

            expect(response?.status(), path).toBe(200);
        }
    });

    for (const account of seededRoleJourneys) {
        test(`keeps the ${account.label} seeded workspace usable`, async ({ page }) => {
            test.setTimeout(90_000);
            await signInAs(page, account.email);
            await expect(page.getByRole('link', { name: account.dashboardCta, exact: true })).toBeVisible({
                timeout: 15_000,
            });

            for (const path of ['/dashboard', '/profile', '/notifications', account.path]) {
                const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 90_000 });

                expect(response?.status(), `${account.label} ${path}`).toBe(200);
            }
        });
    }

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
        test.setTimeout(60_000);
        await signIn(page);
        await page.goto('/settings/general');
        await page.goto('/security/reauthenticate');
        await page.getByLabel('Password').fill(adminPassword);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Confirm' }).click()]);

        await page.goto('/settings/general');
        await page.getByLabel('Locale').selectOption('ar');
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('#app > div[dir="rtl"]')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 15_000 });

        await page.goto('/settings/general');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr', { timeout: 15_000 });
        await expect(page.locator('#app > div[dir="ltr"]')).toBeVisible({ timeout: 15_000 });
    });
});
