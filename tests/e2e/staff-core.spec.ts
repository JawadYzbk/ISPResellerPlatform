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
    { label: 'collector', email: 'collector@example.com', path: '/field', dashboardCta: 'Find customers' },
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
    await restoreEnglishProfile(page);
    await page.goto('/dashboard');
}

async function restoreEnglishProfile(page: Page): Promise<void> {
    await page.goto('/profile');
    await page.getByRole('combobox').click();
    await page.getByRole('option', { name: /^(English|Anglais|الإنجليزية)$/ }).click();
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().endsWith('/profile') && response.request().method() === 'PATCH',
        ),
        page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click(),
    ]);
    await expect(page.getByRole('combobox')).toContainText(/^(English|Anglais|الإنجليزية)$/);
}

test.describe('staff core journeys', () => {
    test('keeps the login layout in one full-height shell', async ({ page }) => {
        await page.goto('/login');

        const viewport = page.viewportSize();
        const brandPanel = page.getByTestId('auth-brand-panel');
        const formPanel = page.getByTestId('auth-form-panel');
        const [brandBox, formBox] = await Promise.all([brandPanel.boundingBox(), formPanel.boundingBox()]);

        expect(viewport).not.toBeNull();
        expect(brandBox).not.toBeNull();
        expect(formBox).not.toBeNull();
        expect(brandBox?.height).toBeGreaterThanOrEqual((viewport?.height ?? 0) - 2);
        expect(formBox?.height).toBeGreaterThanOrEqual((viewport?.height ?? 0) - 2);
        await expect(page.locator('.auth-root')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('.auth-ambient')).toBeVisible();
        await expect(page.locator('.auth-orb-one')).toBeVisible();
    });

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
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await signIn(page);

        await expect(page.getByRole('heading', { name: 'Your operations at a glance.' })).toBeVisible();
        await expect(page.getByText('Welcome back', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Owner finance' })).toBeVisible();
        await expect(page.getByText('Collection rate', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Manager attention queue' })).toBeVisible();
        expect(pageErrors).not.toContain('Unable to encrypt history');
        expect(pageErrors).not.toContain('Cannot convert undefined or null to object');
        await page.goto('/partners/commercial');
        await expect(page).toHaveURL(/\/partners\/commercial/);
        await expect(page.getByRole('heading', { name: 'Prices and settlements' })).toBeVisible({ timeout: 15_000 });
    });

    test('creates a reseller price book entry from the commercial workspace', async ({ page }) => {
        await signIn(page);
        await page.goto('/partners/commercial');

        const code = `BROWSER-RESELLER-${Date.now()}`;
        await page
            .getByLabel('Name')
            .first()
            .fill(`Browser Reseller ${code.slice(-8)}`);
        await page.getByLabel('Code').first().fill(code);
        await page.locator('#partner_currency').click();
        await page.getByRole('option', { name: /USD/ }).click();
        await Promise.all([
            page.waitForURL(/\/partners\/commercial\?partner=/),
            page.getByRole('button', { name: 'Create partner' }).click(),
        ]);

        await expect(page.getByText('Reseller price book', { exact: true })).toBeVisible();
        const pricingSection = page.locator('section').filter({ hasText: 'Reseller price book' });
        await expect(pricingSection.getByRole('button', { name: 'Save price' }).first()).toBeVisible();
        await pricingSection.getByRole('button', { name: 'Save price' }).first().click();
        await expect(page.getByText('Partner price updated.', { exact: true })).toBeVisible();
    });

    test('funds a reseller wallet and completes a settlement from the commercial workspace', async ({ page }) => {
        await signIn(page);
        await page.goto('/partners/commercial');

        const code = `BROWSER-SETTLEMENT-${Date.now()}`;
        await page
            .getByLabel('Name')
            .first()
            .fill(`Browser Settlement ${code.slice(-8)}`);
        await page.getByLabel('Code').first().fill(code);
        await page.locator('#partner_currency').click();
        await page.getByRole('option', { name: /USD/ }).click();
        await Promise.all([
            page.waitForURL(/\/partners\/commercial\?partner=/),
            page.getByRole('button', { name: 'Create partner' }).click(),
        ]);

        await expect(page.getByRole('heading', { name: 'Wallet operations' })).toBeVisible();
        await page.getByLabel(/Amount \(USD\)/).fill('100.00');
        await page.getByRole('button', { name: 'Fund wallet' }).click();
        await expect(page.getByText('Partner wallet funded.', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Create statement' }).click();
        await expect(page.getByText('Settlement statement created.', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Approve' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Approve statement' }).click();
        await expect(page.getByText('Settlement approved.', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Pay settlement' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Pay settlement' }).click();
        await expect(page.getByText('Settlement paid.', { exact: true })).toBeVisible();
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
        await page.goto('/settings/readiness');
        await expect(page.getByRole('heading', { name: 'Pilot readiness' })).toBeVisible();
        await expect(page.getByText('Tenant logo', { exact: true })).toBeVisible();
    });

    test('renders the billing, FX, reports, and messaging workspaces', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);

        await page.goto('/customers');
        const openCustomer = page.getByRole('link', { name: 'Open', exact: true }).first();
        await Promise.all([page.waitForURL('**/customers/**'), openCustomer.click()]);
        await expect(page.getByRole('link', { name: 'Take payment', exact: true })).toBeVisible();
        await Promise.all([
            page.waitForURL('**/payments/create'),
            page.getByRole('link', { name: 'Take payment', exact: true }).click(),
        ]);
        await expect(page.getByRole('heading', { name: 'Record payment' })).toBeVisible();
        await expect(page.getByLabel('Payment currency')).toBeVisible();
        await expect(page.getByLabel('Conversion rounding')).toBeVisible();

        await page.goto('/billing/exchange-rates');
        await expect(page.getByRole('heading', { name: 'Exchange rates' })).toBeVisible();
        await expect(page.getByText('Frankfurter market rates', { exact: true })).toBeVisible();

        await page.goto('/billing/credit-notes');
        await expect(page.getByRole('heading', { name: 'Credit notes' })).toBeVisible();
        await expect(page.getByLabel('Search credit note or customer')).toBeVisible();

        await page.goto('/reports/finance');
        await expect(page.getByRole('heading', { name: 'Collections and revenue' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Download CSV' })).toBeVisible();

        await page.goto('/reports/operations');
        await expect(page.getByRole('heading', { name: 'Network and field health' })).toBeVisible();

        await page.goto('/settings/whatsapp');
        await expect(page.getByRole('heading', { name: 'WhatsApp delivery' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Send one test message' })).toBeVisible();
    });

    test('opens payment creation without Web Crypto randomUUID', async ({ page }) => {
        const pageErrors: Error[] = [];
        page.on('pageerror', (error) => pageErrors.push(error));
        await page.addInitScript(() => {
            if (globalThis.crypto) {
                Object.defineProperty(globalThis.crypto, 'randomUUID', { configurable: true, value: undefined });
            }
        });

        await signIn(page);
        await page.goto('/customers');
        await Promise.all([
            page.waitForURL('**/customers/**'),
            page.getByRole('link', { name: 'Open', exact: true }).first().click(),
        ]);
        await page.getByRole('link', { name: 'Take payment', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Record payment' })).toBeVisible();
        expect(pageErrors.map((error) => error.message)).not.toContain('crypto.randomUUID is not a function');
    });

    test('exposes catalog editing and inventory setup controls', async ({ page }) => {
        await signIn(page);

        await page.goto('/plans');
        await expect(page.getByRole('heading', { name: 'Plans' })).toBeVisible();
        await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
        await expect(page.getByRole('heading', { name: 'Edit plan' })).toBeVisible();

        await page.goto('/operations/inventory');
        await expect(page.getByText('Set up inventory')).toBeVisible();
        await expect(page.getByText('New inventory item')).toBeVisible();
        await expect(page.getByText('New warehouse or van')).toBeVisible();

        await page.goto('/settings/ticket-responses');
        await expect(page.getByRole('heading', { name: 'Ticket responses' })).toBeVisible();
    });

    test('creates and edits add-ons and inventory catalog records', async ({ page }) => {
        await signIn(page);

        await page.goto('/plans');
        const addonSection = page.locator('section').filter({ hasText: 'Recurring or one-off extras' }).first();
        const addonName = `Browser add-on ${Date.now()}`;
        const updatedAddonName = `${addonName} updated`;

        await addonSection.getByLabel('Name').fill(addonName);
        await addonSection.getByLabel('Price').fill('7.50');
        await addonSection.getByRole('button', { name: 'Add add-on' }).click();
        await expect(page.getByText(addonName, { exact: true })).toBeVisible();

        await addonSection.getByRole('button', { name: `Edit ${addonName}` }).click();
        await addonSection.getByLabel('Name').fill(updatedAddonName);
        await addonSection.getByRole('button', { name: 'Save add-on' }).click();
        await expect(page.getByText(updatedAddonName, { exact: true })).toBeVisible();

        await page.goto('/operations/inventory');
        const setupSection = page
            .locator('section')
            .filter({ hasText: 'Create the item and storage records first' })
            .first();
        const itemSku = `E2E-${Date.now()}`;
        const itemName = `Browser inventory ${itemSku}`;
        const warehouseCode = `E2E${Date.now().toString().slice(-5)}`;
        const warehouseName = `Browser warehouse ${warehouseCode}`;
        const itemForm = setupSection.locator('form').filter({ hasText: 'New inventory item' });
        const warehouseForm = setupSection.locator('form').filter({ hasText: 'New warehouse or van' });

        await itemForm.getByLabel('SKU').fill(itemSku);
        await itemForm.getByLabel('Name').fill(itemName);
        await itemForm.getByLabel('Category').fill('test-equipment');
        await itemForm.getByRole('button', { name: 'Create item' }).click();
        await expect(page.getByText(itemName, { exact: true })).toBeVisible();

        await warehouseForm.getByLabel('Name').fill(warehouseName);
        await warehouseForm.getByLabel('Code').fill(warehouseCode);
        await warehouseForm.getByRole('button', { name: 'Create storage' }).click();
        await expect(page.getByText(warehouseName, { exact: true })).toBeVisible();
    });

    test('exposes protected operator role controls to the tenant owner', async ({ page }) => {
        await signIn(page);

        await page.goto('/settings/users');
        await expect(page.getByRole('heading', { name: 'Users and invitations' })).toBeVisible();
        const editRole = page.getByRole('button', { name: 'Edit role', exact: true }).first();
        await expect(editRole).toBeVisible();
        await editRole.click();
        await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    });

    test('keeps a dragged work order on the calendar after rescheduling', async ({ page }) => {
        await signIn(page);

        await page.goto('/operations/work-orders/calendar');
        await expect(page.getByRole('heading', { name: 'Work-order calendar' })).toBeVisible();
        const draggableOrder = page.locator('[draggable="true"]').first();
        await expect(draggableOrder).toBeVisible();
        await draggableOrder.dragTo(page.locator('.min-h-24').nth(10));

        await expect(page).toHaveURL(/\/operations\/work-orders\/calendar/);
        await expect(page.getByRole('heading', { name: 'Work-order calendar' })).toBeVisible();
        await expect(page.locator('[draggable="true"]').first()).toBeVisible();
    });

    test('manages a temporary WhatsApp Web.js account from the browser', async ({ page }) => {
        test.setTimeout(120_000);
        const bridgeHealthUrl = process.env.E2E_WHATSAPP_BRIDGE_HEALTH_URL ?? 'http://127.0.0.1:3001/health';
        const bridgeHealth = await page.request.get(bridgeHealthUrl, { timeout: 1_500 }).catch(() => null);
        test.skip(
            bridgeHealth?.ok() !== true,
            'The optional WhatsApp Web.js bridge is not healthy in this environment.',
        );

        await signIn(page);
        await page.goto('/settings/whatsapp');
        await expect(page.getByRole('heading', { name: 'WhatsApp delivery' })).toBeVisible();

        const label = `E2E temporary ${Date.now()}`;
        const accountCard = page.locator('div.rounded-2xl.border.border-line.bg-white.p-5').filter({ hasText: label });

        try {
            await page.getByPlaceholder('Billing phone').fill(label);
            await page.getByRole('button', { name: 'Add account', exact: true }).click();
            await expect(page.getByTestId('flash-toast')).toContainText('WhatsApp account added.');
            await expect(accountCard.getByText(label, { exact: true })).toBeVisible({ timeout: 15_000 });
            await expect(accountCard.getByRole('button', { name: 'Disconnect and pair again' })).toBeVisible();

            await accountCard.getByRole('button', { name: 'Disconnect and pair again' }).click();
            await expect(accountCard).toContainText(/Waiting for QR scan|Disconnected|Starting/);
        } finally {
            if (await accountCard.getByText(label, { exact: true }).count()) {
                await accountCard.getByRole('button', { name: 'Delete account' }).click();
                const deleteDialog = page.getByRole('alertdialog');
                await expect(deleteDialog).toBeVisible();
                const deleteResponse = page.waitForResponse(
                    (response) =>
                        response.url().includes('/settings/whatsapp/accounts/') &&
                        response.request().method() === 'DELETE',
                );
                await deleteDialog.getByRole('button', { name: 'Delete account', exact: true }).click();
                expect((await deleteResponse).status()).toBeLessThan(400);
                await expect(page.getByTestId('flash-toast')).toContainText('WhatsApp account deleted.');
                await page.reload();
                await expect(accountCard).toHaveCount(0);
            }
        }
    });

    test('shows the customer payment grid by month', async ({ page }) => {
        await signIn(page);
        await page.goto('/customers');
        await Promise.all([
            page.waitForURL('**/customers/**'),
            page.getByRole('link', { name: 'Open', exact: true }).first().click(),
        ]);

        await expect(page.getByTestId('customer-payment-grid')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Monthly payment grid' })).toBeVisible();
        await expect(page.getByLabel('Payment year')).toBeVisible();
        await expect(page.locator('[data-testid^="payment-month-"]')).toHaveCount(12);
    });

    test('localizes the customer detail payment grid in French', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);
        await page.goto('/profile');
        try {
            await page.getByRole('combobox').click();
            await page.getByRole('option', { name: /^(French|Français|الفرنسية)$/ }).click();
            await Promise.all([
                page.waitForResponse(
                    (response) => response.url().endsWith('/profile') && response.request().method() === 'PATCH',
                ),
                page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click(),
            ]);
            await page.goto('/customers');
            await expect(page.getByRole('heading', { name: 'Clients' })).toBeVisible();
            await Promise.all([
                page.waitForURL('**/customers/**'),
                page
                    .getByRole('link', { name: /^(Open|Ouvrir)$/ })
                    .first()
                    .click(),
            ]);
            await expect(page.getByRole('heading', { name: 'Grille mensuelle des paiements' })).toBeVisible();
            await expect(page.getByLabel('Année de paiement')).toBeVisible();
        } finally {
            await restoreEnglishProfile(page);
        }
    });

    test('localizes finance and operations reports in French', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);
        await page.goto('/profile');
        try {
            await page.getByRole('combobox').click();
            await page.getByRole('option', { name: /^(French|Français|الفرنسية)$/ }).click();
            await Promise.all([
                page.waitForResponse(
                    (response) => response.url().endsWith('/profile') && response.request().method() === 'PATCH',
                ),
                page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click(),
            ]);
            await page.goto('/reports/finance');
            await expect(page.getByRole('heading', { name: 'Encaissements et revenus' })).toBeVisible();
            await page.goto('/reports/operations');
            await expect(page.getByRole('heading', { name: 'État du réseau et du terrain' })).toBeVisible();
        } finally {
            await restoreEnglishProfile(page);
        }
    });

    test('localizes workspace currency and WhatsApp setup in French', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);
        await page.goto('/profile');
        try {
            await page.getByRole('combobox').click();
            await page.getByRole('option', { name: /^(French|Français|الفرنسية)$/ }).click();
            await Promise.all([
                page.waitForResponse(
                    (response) => response.url().endsWith('/profile') && response.request().method() === 'PATCH',
                ),
                page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click(),
            ]);
            await page.goto('/settings/general');
            await expect(page.getByRole('heading', { name: 'Paramètres de l’espace de travail' })).toBeVisible();
            await expect(page.getByText('Devise de base', { exact: true })).toBeVisible();
            await page.goto('/settings/whatsapp');
            await expect(page.getByRole('heading', { name: 'Livraison WhatsApp' })).toBeVisible();
            await expect(page.getByRole('heading', { name: 'Jumelage et livraison' })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Ajouter un compte' })).toBeVisible();
            await expect(
                page
                    .getByText(
                        /^(Prêt|Pont inaccessible|En attente de démarrage|En attente du scan QR|Démarrage|Authentifié|Déconnecté|Configuration requise|Configuré|Désactivé|Inconnu)$/,
                    )
                    .first(),
            ).toBeVisible();
            await page
                .getByRole('combobox', { name: /^(Assigned job|Tâche affectée)$/ })
                .first()
                .click();
            await expect(page.getByRole('option', { name: 'Livraison générale' })).toBeVisible();
            await page.keyboard.press('Escape');
        } finally {
            await restoreEnglishProfile(page);
        }
    });

    test('uses shadcn selects on desktop and the native fallback only on mobile', async ({ page }) => {
        await signIn(page);
        await page.goto('/settings/general');
        await expect(page.getByRole('heading', { name: 'Workspace settings' })).toBeVisible();
        await expect(page.locator('select:not([aria-hidden="true"])')).toHaveCount(0);

        await page.getByLabel('Locale').click();
        await expect(page.getByRole('option', { name: 'Arabic' })).toBeVisible();
        await page.getByRole('option', { name: 'English' }).click();

        await page.goto('/settings/whatsapp');
        await expect(page.getByRole('heading', { name: 'WhatsApp delivery' })).toBeVisible();
        await expect(page.locator('select:not([aria-hidden="true"])')).toHaveCount(0);

        await page.goto('/settings/general');
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.locator('select:not([aria-hidden="true"])')).toHaveCount(3);
        await expect(page.getByLabel('Locale')).toHaveValue('en');
    });

    test('selects a searched currency with the mouse', async ({ page }) => {
        await signIn(page);
        await page.goto('/settings/general');

        await page.getByLabel('Base currency').click();
        await expect(page.locator('[cmdk-item]').filter({ hasText: 'USD' }).first()).toContainText('USD');
        await expect(page.locator('[cmdk-item]').filter({ hasText: 'EUR' }).first()).toContainText('EUR');
        await expect(page.locator('[cmdk-item]').filter({ hasText: 'LBP' }).first()).toContainText('LBP');
        await page.locator('input[aria-label="Search currencies"]').fill('euro');
        await page.getByRole('option', { name: /EUR.*Euro/ }).click();

        await expect(page.getByLabel('Base currency')).toContainText('EUR');
        await expect(page.getByLabel('Base currency')).toContainText('Euro');
    });

    test('uses an accessible confirmation dialog for destructive actions', async ({ page }) => {
        await signIn(page);
        await page.goto('/billing/payments');

        const reverseButton = page.getByRole('button', { name: 'Reverse', exact: true }).first();
        await expect(reverseButton).toBeVisible();
        await reverseButton.click();

        await expect(page.getByRole('alertdialog')).toBeVisible();
        await expect(page.getByRole('heading', { name: /Reverse payment/ })).toBeVisible();
        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page.getByRole('alertdialog')).toBeHidden();
    });

    test('saves workspace settings together with a logo upload', async ({ page }) => {
        await signIn(page);
        await page.goto('/security/reauthenticate');
        await page.getByLabel('Password').fill(adminPassword);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Confirm' }).click()]);

        await page.goto('/settings/general');
        await page.locator('input[type="file"]').setInputFiles({
            name: 'e2e-logo.png',
            mimeType: 'image/png',
            buffer: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                'base64',
            ),
        });
        const settingsResponse = page.waitForResponse(
            (response) => response.url().endsWith('/settings/general') && response.request().method() === 'POST',
        );
        await page.getByRole('button', { name: 'Save settings' }).click();
        expect((await settingsResponse).status()).toBeLessThan(400);
        await expect(page).toHaveURL(/\/settings\/general/);
        await expect(page.getByRole('heading', { name: 'Workspace settings' })).toBeVisible();
        await expect(page.getByText('The name field is required.')).toHaveCount(0);
        await expect(page.getByText('The base currency field is required.')).toHaveCount(0);
    });

    test('applies the workspace language when no personal override is selected', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);
        await page.goto('/security/reauthenticate');
        await page.getByLabel('Password').fill(adminPassword);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Confirm' }).click()]);

        try {
            await page.goto('/profile');
            await page.getByRole('combobox').click();
            await page.getByRole('option').first().click();
            await page.locator('#save-profile').click();

            await page.goto('/settings/general');
            await page.locator('#workspace-locale').click();
            await page.getByRole('option', { name: 'French' }).click();
            await Promise.all([
                page.waitForResponse(
                    (response) =>
                        response.url().endsWith('/settings/general') && response.request().method() === 'POST',
                ),
                page.locator('#save-workspace-settings').click(),
            ]);
            await page.goto('/dashboard');
            await expect(page.getByRole('heading', { name: 'Vos opérations en un coup d’œil.' })).toBeVisible();
        } finally {
            if (!page.isClosed()) {
                await page.goto('/settings/general');
                await page.locator('#workspace-locale').click();
                await page.getByRole('option').first().click();
                await page.locator('#save-workspace-settings').click();
                await page.goto('/profile');
                await page.locator('#profile-locale').click();
                await page.getByRole('option').first().click();
                await page.locator('#save-profile').click();
            }
        }
    });

    test('highlights the active sidebar section for nested pages', async ({ page }) => {
        await signIn(page);
        const sidebar = page.locator('aside nav');

        await page.goto('/partners/commercial?partner=demo');
        await expect(sidebar.getByRole('link', { name: 'Partners' })).toHaveAttribute('aria-current', 'page');

        await page.goto('/billing/invoices');
        await expect(sidebar.getByRole('link', { name: 'Billing' })).toHaveAttribute('aria-current', 'page');
        await expect(sidebar.getByRole('link', { name: 'Payments' })).not.toHaveAttribute('aria-current', 'page');

        await page.goto('/operations/work-orders/calendar');
        await expect(sidebar.getByRole('link', { name: 'Work-order calendar' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        await expect(sidebar.getByRole('link', { name: 'Work orders', exact: true })).not.toHaveAttribute(
            'aria-current',
            'page',
        );

        await page.goto('/settings/locations');
        await expect(sidebar.getByRole('link', { name: 'Settings' })).toHaveAttribute('aria-current', 'page');
    });

    test('opens the profile menu and notifications center from the header', async ({ page }) => {
        await signIn(page);
        await expect(page.getByRole('heading', { name: 'Your operations at a glance.' })).toBeVisible();

        await page.getByRole('button', { name: 'Open account menu' }).click();
        await Promise.all([page.waitForURL('**/profile'), page.getByRole('menuitem', { name: 'Profile' }).click()]);
        await expect(page.getByRole('heading', { name: /^(Your profile|Votre profil|ملفك الشخصي)$/ })).toBeVisible();

        await Promise.all([
            page.waitForURL('**/notifications'),
            page.getByRole('link', { name: 'Open notifications center' }).click(),
        ]);
        await expect(page.getByRole('heading', { name: 'Notifications & attention' })).toBeVisible();
    });

    test('saves French as the profile language', async ({ page }) => {
        await signIn(page);
        await page.goto('/profile');
        try {
            await expect(
                page.getByRole('heading', { name: /^(Your profile|Votre profil|ملفك الشخصي)$/ }),
            ).toBeVisible();
            await page.getByRole('combobox').click();
            await page.getByRole('option', { name: /^(French|Français|الفرنسية)$/ }).click();
            await page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click();
            await expect(page.getByTestId('flash-toast')).toContainText('Profile updated.');
            await expect(page.getByText('Updated', { exact: true })).toBeVisible();
            await expect(page.getByRole('combobox')).toContainText(/^(French|Français|الفرنسية)$/);
        } finally {
            await restoreEnglishProfile(page);
        }
    });

    test('shows validation feedback when an action is rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('/profile');
        await page.getByLabel('Name').fill('');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(page.getByTestId('flash-toast')).toContainText('The name field is required.');
        await expect(page.locator('.field-error')).toContainText('The name field is required.');
    });

    test('renders the shared shell in French after switching locale', async ({ page }) => {
        test.setTimeout(90_000);
        await signIn(page);
        await page.goto('/profile');
        try {
            await page.getByRole('combobox').click();
            await page.getByRole('option', { name: /^(French|Français|الفرنسية)$/ }).click();
            await Promise.all([
                page.waitForResponse(
                    (response) => response.url().endsWith('/profile') && response.request().method() === 'PATCH',
                ),
                page.getByRole('button', { name: /^(Save profile|Enregistrer le profil|حفظ الملف الشخصي)$/ }).click(),
            ]);
            await page.goto('/dashboard');
            await expect(page.getByRole('link', { name: 'Clients' })).toBeVisible();
            await expect(page.getByRole('heading', { name: 'Vos opérations en un coup d’œil.' })).toBeVisible();
            await page.getByRole('button', { name: 'Ouvrir le menu du compte' }).click();
            await expect(page.getByRole('menuitem', { name: 'Paramètres de l’espace de travail' })).toBeVisible();
        } finally {
            await restoreEnglishProfile(page);
        }
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
            '/settings/readiness',
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
        await expect(page.getByRole('heading', { name: 'Your operations at a glance.' })).toBeVisible();

        await expect(page.getByRole('navigation', { name: 'Field navigation' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Customers' }).last()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Payments' }).last()).toBeVisible();

        await page.context().setOffline(true);
        await expect(page.getByTestId('offline-banner')).toContainText('Offline.');
        await page.context().setOffline(false);
    });

    test('opens the collector desk with a searchable customer list and offline queue status', async ({ page }) => {
        await signIn(page);
        await page.goto('/field');

        await expect(page.getByRole('heading', { name: 'Collector desk' })).toBeVisible();
        await expect(page.getByLabel('Search field customers')).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Field payment currency' })).toBeVisible();
        await expect
            .poll(() =>
                page.evaluate(
                    () =>
                        new Promise<boolean>((resolve) => {
                            const hasEncryptedFallback = Object.keys(localStorage)
                                .filter((key) => key.startsWith('isp-manager-field:'))
                                .some((key) => {
                                    try {
                                        const record = JSON.parse(localStorage.getItem(key) ?? '{}');
                                        return (
                                            record.version === 2 &&
                                            typeof record.iv === 'string' &&
                                            typeof record.ciphertext === 'string' &&
                                            !('cached_snapshot' in record)
                                        );
                                    } catch {
                                        return false;
                                    }
                                });
                            const request = indexedDB.open('isp-manager-field', 1);
                            request.onerror = () => resolve(hasEncryptedFallback);
                            request.onsuccess = () => {
                                const read = request.result
                                    .transaction('state', 'readonly')
                                    .objectStore('state')
                                    .getAll();
                                read.onerror = () => resolve(hasEncryptedFallback);
                                read.onsuccess = () =>
                                    resolve(
                                        hasEncryptedFallback ||
                                            read.result.some(
                                                (record) =>
                                                    record.version === 2 &&
                                                    typeof record.iv === 'string' &&
                                                    typeof record.ciphertext === 'string' &&
                                                    !('cached_snapshot' in record),
                                            ),
                                    );
                            };
                        }),
                ),
            )
            .toBe(true);

        await page.getByRole('button', { name: 'Clear device data' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await page.getByRole('button', { name: 'Clear device data', exact: true }).last().click();
        await expect(page.getByRole('status')).toContainText('Field data was cleared');

        await page.context().setOffline(true);
        await expect(page.getByRole('status').filter({ hasText: /offline/i })).toBeVisible();
        await page.context().setOffline(false);
    });

    test('attempts a restored field payment queue when the desk reopens online', async ({ page }) => {
        await signIn(page);
        await page.goto('/billing/shifts');
        const openShift = page.getByRole('button', { name: 'Open cash shift' });
        if (await openShift.count()) await openShift.click();
        await expect(page.getByRole('heading', { name: /Opened/ })).toBeVisible();

        await page.goto('/field');
        await expect(page.getByRole('heading', { name: 'Collector desk' })).toBeVisible();

        await page.getByTestId('field-customer-row').first().click();
        await page.getByLabel('Field payment amount').fill('1');
        await page.context().setOffline(true);
        await page.getByRole('button', { name: 'Save payment to device' }).click();
        await expect(page.getByText('Payment saved on this device')).toBeVisible();
        await page.context().setOffline(false);

        const pushRequest = page.waitForRequest(
            (request) => request.url().endsWith('/field/push') && request.method() === 'POST',
        );
        await page.reload();
        await expect(page.getByRole('heading', { name: 'Collector desk' })).toBeVisible();
        expect((await pushRequest).postDataJSON().items).toHaveLength(1);
    });

    test('renders the workspace in right-to-left mode when configured', async ({ page }) => {
        test.setTimeout(60_000);
        await signIn(page);
        await page.goto('/settings/general');
        await page.goto('/security/reauthenticate');
        await page.getByLabel('Password').fill(adminPassword);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', { name: 'Confirm' }).click()]);

        await page.goto('/settings/general');
        await page.getByLabel('Locale').click();
        await page.getByRole('option', { name: 'Arabic' }).click();
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('#app > div[dir="rtl"]')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 15_000 });

        await page.goto('/settings/general');
        await page.getByLabel('Locale').click();
        await page.getByRole('option', { name: 'English' }).click();
        await page.getByRole('button', { name: 'Save settings' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr', { timeout: 15_000 });
        await expect(page.locator('#app > div[dir="ltr"]')).toBeVisible({ timeout: 15_000 });
    });
});
