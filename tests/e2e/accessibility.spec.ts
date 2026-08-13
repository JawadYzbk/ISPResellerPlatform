import { expect, test, type Page } from '@playwright/test';

const email = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const password = process.env.E2E_ADMIN_PASSWORD ?? 'password';
const collectorEmail = process.env.E2E_COLLECTOR_EMAIL ?? 'collector@example.com';

async function auditPage(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await expect(page.locator('main:visible'), `${path} should render its main content`).toBeVisible();

    const unnamed = await page
        .locator('button:visible, a:visible, input:visible, select:visible, textarea:visible')
        .evaluateAll((elements) => {
            const accessibleName = (element: Element): string => {
                const labelledBy = element.getAttribute('aria-labelledby');
                const labelledByText = labelledBy
                    ?.split(/\s+/)
                    .map((id) => document.getElementById(id)?.textContent ?? '')
                    .join(' ');
                const label =
                    element.getAttribute('aria-label') ??
                    labelledByText ??
                    (element instanceof HTMLInputElement ||
                    element instanceof HTMLSelectElement ||
                    element instanceof HTMLTextAreaElement
                        ? element.labels?.[0]?.textContent
                        : null) ??
                    element.closest('label')?.textContent ??
                    element.getAttribute('title') ??
                    element.textContent ??
                    '';

                return label.replace(/\s+/g, ' ').trim();
            };

            return elements
                .filter((element) => accessibleName(element) === '')
                .map((element) => ({ tag: element.tagName.toLowerCase(), html: element.outerHTML.slice(0, 180) }));
        });

    expect(unnamed, `${path} has unnamed controls`).toEqual([]);

    const implicitSubmitButtons = await page
        .locator('form button:visible:not([type])')
        .evaluateAll((elements) => elements.map((element) => element.outerHTML.slice(0, 180)));

    expect(implicitSubmitButtons, `${path} has buttons without an explicit type`).toEqual([]);

    const unannouncedErrors = await page
        .locator('[class~="field-error"]:visible:not([role="alert"])')
        .evaluateAll((elements) => elements.map((element) => element.outerHTML.slice(0, 180)));

    expect(unannouncedErrors, `${path} has validation errors without an alert role`).toEqual([]);
    await expect(page.locator('main h1:visible'), `${path} should expose a page heading`).toHaveCount(1);
}

async function findDetailPath(page: Page, indexPath: string, pattern: RegExp): Promise<string | null> {
    await page.goto(indexPath);
    await expect(page.locator('main h1:visible'), `${indexPath} should render its page heading`).toHaveCount(1);
    const href = await page.locator('a[href]').evaluateAll(
        (links, source) => links.map((link) => link.getAttribute('href') ?? '').find((href) => source.test(href)),
        pattern,
    );

    return href ?? null;
}

test('keeps representative workspace controls named for assistive technology', async ({ page }) => {
    test.setTimeout(120_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    const detailPaths: string[] = [];
    const customerPath = await findDetailPath(page, '/customers', /^\/customers\/(?!create$)[^/]+$/);
    if (customerPath) {
        detailPaths.push(customerPath);
    }

    for (const [indexPath, pattern] of [
        ['/services', /^\/services\/[^/]+$/],
        ['/billing/invoices', /^\/billing\/invoices\/[^/]+$/],
        ['/billing/payments', /^\/billing\/payments\/[^/]+$/],
        ['/operations/tickets', /^\/operations\/tickets\/[^/]+$/],
        ['/operations/work-orders', /^\/operations\/work-orders\/[^/]+$/],
        ['/operations/routers', /^\/operations\/routers\/[^/]+$/],
        ['/operations/pops', /^\/operations\/pops\/[^/]+$/],
        ['/operations/topology/buildings', /^\/operations\/topology\/buildings\/[^/]+$/],
        ['/plans', /^\/plans\/[^/]+\/edit$/],
    ] as const) {
        const detailPath = await findDetailPath(page, indexPath, pattern);
        if (detailPath) {
            detailPaths.push(detailPath);
        }
    }

    for (const path of [
        '/dashboard',
        '/customers',
        '/customers/create',
        '/plans',
        '/services',
        '/settings/general',
        '/settings',
        '/settings/locations',
        '/settings/integrations',
        '/settings/readiness',
        '/settings/notification-templates',
        '/settings/ticket-responses',
        '/settings/users',
        '/settings/whatsapp',
        '/operations/collector-check-ins',
        '/operations/collector-routes',
        '/operations/collector-tasks',
        '/operations/collector-custody',
        '/operations/tickets',
        '/operations/work-orders',
        '/operations/inventory',
        '/operations/imports',
        '/operations/credentials',
        '/operations/suppliers',
        '/operations/topology/buildings',
        '/operations/optical',
        '/operations/sessions',
        '/operations/incidents',
        '/operations/network-commands',
        '/operations/routers',
        '/operations/pops',
        '/operations/ip-pools',
        '/operations/work-orders/calendar',
        '/billing/invoices',
        '/billing/payments',
        '/billing/credit-notes',
        '/billing/exchange-rates',
        '/billing/shifts',
        '/reports/finance',
        '/reports/operations',
        '/partners/commercial',
        '/notifications',
        '/profile',
        ...detailPaths,
    ]) {
        await auditPage(page, path);
    }
});

test('keeps guest authentication pages accessible', async ({ page }) => {
    await auditPage(page, '/login');
    await auditPage(page, '/forgot-password');
});

test('connects password recovery errors to their controls', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.locator('#email').fill('');
    await page.locator('form button[type="submit"]').click();

    const emailField = page.locator('#email');
    await expect(emailField).toHaveAttribute('aria-invalid', 'true');
    await expect(emailField).toHaveAttribute('aria-describedby', 'email-error');
    await expect(page.locator('#email-error')).toHaveAttribute('role', 'alert');
});

test('connects customer validation messages to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/customers/create');
    await page.locator('#first_name').fill('Accessibility');
    await page.locator('#phone').fill('+96170123456');
    await page.locator('#username').fill('invalid username');
    await page.locator('form button[type="submit"]').click();

    const username = page.locator('#username');
    await expect(username).toHaveAttribute('aria-invalid', 'true');
    await expect(username).toHaveAttribute('aria-describedby', 'username-error');
    await expect(page.locator('#username-error')).toHaveAttribute('role', 'alert');
});

test('connects searchable customer errors to the combobox', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/billing/invoices/create');
    await page.locator('form button[type="submit"]').click();

    const amount = page.locator('#amount');
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    await expect(amount).toHaveAttribute('aria-describedby', 'amount-error');
    await expect(page.locator('#amount-error')).toHaveAttribute('role', 'alert');

    await amount.fill('10');
    await page.locator('form button[type="submit"]').click();

    const customer = page.locator('#customer_id');
    await expect(customer).toHaveAttribute('aria-invalid', 'true');
    await expect(customer).toHaveAttribute('aria-describedby', 'customer_id-error');
    await expect(page.locator('#customer_id-error')).toHaveAttribute('role', 'alert');
});

test('connects customer edit errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/customers');
    const customerPath = await findDetailPath(page, '/customers', /^\/customers\/(?!create$)[^/]+$/);
    expect(customerPath).not.toBeNull();
    await page.goto(`${customerPath}/edit`);
    await page.locator('#address').fill('a'.repeat(501));
    await page.locator('form button[type="submit"]').click();

    const address = page.locator('#address');
    await expect(address).toHaveAttribute('aria-invalid', 'true');
    await expect(address).toHaveAttribute('aria-describedby', 'address-error');
    await expect(page.locator('#address-error')).toHaveAttribute('role', 'alert');
});

test('connects plan pricing errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/plans/create');
    await page.locator('#name').fill('Accessibility plan');
    await page.locator('form button[type="submit"]').click();

    const createAmount = page.locator('#amount');
    await expect(createAmount).toHaveAttribute('aria-invalid', 'true');
    await expect(createAmount).toHaveAttribute('aria-describedby', 'amount-error');
    await expect(page.locator('#amount-error')).toHaveAttribute('role', 'alert');

    const editPath = await findDetailPath(page, '/plans', /^\/plans\/[^/]+\/edit$/);
    expect(editPath).not.toBeNull();
    await page.goto(editPath!);
    await page.locator('#amount').fill('');
    await page.locator('form button[type="submit"]').click();

    const editAmount = page.locator('#amount');
    await expect(editAmount).toHaveAttribute('aria-invalid', 'true');
    await expect(editAmount).toHaveAttribute('aria-describedby', 'amount-error');
    await expect(page.locator('#amount-error')).toHaveAttribute('role', 'alert');
});

test('connects payment validation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    const customerPath = await findDetailPath(page, '/customers', /^\/customers\/(?!create$)[^/]+$/);
    expect(customerPath).not.toBeNull();
    await page.goto(`${customerPath}/payments/create`);
    await page.locator('form button[type="submit"]').click();

    const amount = page.locator('#amount');
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    await expect(amount).toHaveAttribute('aria-describedby', 'amount-error');
    await expect(page.locator('#amount-error')).toHaveAttribute('role', 'alert');
});

test('connects workspace settings errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/security/reauthenticate');
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Confirm' }).click(),
    ]);

    await page.goto('/settings/general');
    await page.locator('#workspace-name').fill('a'.repeat(256));
    await page.locator('#save-workspace-settings').click();

    const name = page.locator('#workspace-name');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', 'name-error');
    await expect(page.locator('#name-error')).toHaveAttribute('role', 'alert');
});

test('connects profile validation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/profile');
    await page.locator('#name').fill('a'.repeat(256));
    await page.locator('#save-profile').click();

    const name = page.locator('#name');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', 'name-error');
    await expect(page.locator('#name-error')).toHaveAttribute('role', 'alert');
});

test('connects invitation validation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/security/reauthenticate');
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Confirm' }).click(),
    ]);

    await page.goto('/settings/users');
    const inviteForm = page.locator('form').filter({ has: page.locator('#invite-email') });
    await inviteForm.locator('#invite-email').fill('');
    await inviteForm.locator('button[type="submit"]').click();

    const emailField = page.locator('#invite-email');
    await expect(emailField).toHaveAttribute('aria-invalid', 'true');
    await expect(emailField).toHaveAttribute('aria-describedby', 'invite-email-error');
    await expect(page.locator('#invite-email-error')).toHaveAttribute('role', 'alert');
});

test('connects inventory setup errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/operations/inventory');
    const itemForm = page.locator('form').filter({ has: page.locator('#item-sku') });
    await itemForm.locator('#item-sku').fill('');
    await itemForm.locator('button[type="submit"]').click();

    const sku = page.locator('#item-sku');
    await expect(sku).toHaveAttribute('aria-invalid', 'true');
    await expect(sku).toHaveAttribute('aria-describedby', 'item-sku-error');
    await expect(page.locator('#item-sku-error')).toHaveAttribute('role', 'alert');
});

test('connects exchange-rate errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/billing/exchange-rates');
    await page.locator('#source').fill('');
    await page.locator('form').filter({ has: page.locator('#source') }).locator('button[type="submit"]').click();

    const source = page.locator('#source');
    await expect(source).toHaveAttribute('aria-invalid', 'true');
    await expect(source).toHaveAttribute('aria-describedby', 'source-error');
    await expect(page.locator('#source-error')).toHaveAttribute('role', 'alert');
});

test('connects POP creation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/operations/pops');
    await page.locator('#pop-code').fill('ACCESSIBILITY-POP');
    await page.locator('form').filter({ has: page.locator('#pop-name') }).locator('button[type="submit"]').click();

    const name = page.locator('#pop-name');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', 'pop-name-error');
    await expect(page.locator('#pop-name-error')).toHaveAttribute('role', 'alert');
});

test('connects service validation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    const customerPath = await findDetailPath(page, '/customers', /^\/customers\/(?!create$)[^/]+$/);
    expect(customerPath).not.toBeNull();
    await page.goto(`${customerPath}/services/create`);
    await page.locator('form button[type="submit"]').click();

    const passwordField = page.locator('#password');
    await expect(passwordField).toHaveAttribute('aria-invalid', 'true');
    await expect(passwordField).toHaveAttribute('aria-describedby', 'password-error');
    await expect(page.locator('#password-error')).toHaveAttribute('role', 'alert');
});

test('connects router validation errors to their controls', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.goto('/operations/routers/create');
    await page.locator('form button[type="submit"]').click();

    const name = page.locator('#name');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', 'name-error');
    await expect(page.locator('#name-error')).toHaveAttribute('role', 'alert');
});

test('keeps customer portal sign-in inputs accessible', async ({ page }) => {
    await auditPage(page, '/portal/northline');

    const phone = page.locator('input[type="tel"]');
    await expect(phone).toHaveCount(1);
    await expect(phone).toHaveAttribute('type', 'tel');
    await expect(phone).toHaveAttribute('autocomplete', 'tel');
});

test('keeps the collector workspace accessible to collector accounts', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(collectorEmail);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|field|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await auditPage(page, '/field');
});

test('keeps core text colors and landmarks accessible', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    const audit = await page.evaluate(() => {
        const parseColor = (value: string): [number, number, number] => {
            const hex = value.trim().replace('#', '');
            const normalized = hex.length === 3 ? hex.split('').map((part) => part + part).join('') : hex;

            return [0, 2, 4].map((offset) => Number.parseInt(normalized.slice(offset, offset + 2), 16)) as [
                number,
                number,
                number,
            ];
        };
        const luminance = (color: [number, number, number]): number =>
            color
                .map((channel) => {
                    const normalized = channel / 255;
                    return normalized <= 0.03928
                        ? normalized / 12.92
                        : ((normalized + 0.055) / 1.055) ** 2.4;
                })
                .reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);
        const contrast = (foreground: string, background: string): number => {
            const foregroundLuminance = luminance(parseColor(foreground));
            const backgroundLuminance = luminance(parseColor(background));

            return (Math.max(foregroundLuminance, backgroundLuminance) + 0.05) /
                (Math.min(foregroundLuminance, backgroundLuminance) + 0.05);
        };
        const styles = getComputedStyle(document.documentElement);
        const colors = {
            ink: styles.getPropertyValue('--color-ink').trim(),
            muted: styles.getPropertyValue('--color-muted').trim(),
            brand: styles.getPropertyValue('--color-brand').trim(),
            coral: styles.getPropertyValue('--color-coral').trim(),
        };

        return {
            colors,
            ratios: {
                ink: contrast(colors.ink, '#ffffff'),
                mutedOnWhite: contrast(colors.muted, '#ffffff'),
                mutedOnCanvas: contrast(colors.muted, '#f7f8f6'),
                brand: contrast(colors.brand, '#ffffff'),
                coral: contrast(colors.coral, '#ffffff'),
            },
            landmarks: {
                main: document.querySelectorAll('main#main-content').length,
                navigation: document.querySelectorAll('nav[aria-label]').length,
            },
        };
    });

    expect(audit.colors).toMatchObject({
        ink: expect.stringMatching(/^#[0-9a-f]{6}$/i),
        muted: expect.stringMatching(/^#[0-9a-f]{6}$/i),
        brand: expect.stringMatching(/^#[0-9a-f]{6}$/i),
        coral: expect.stringMatching(/^#[0-9a-f]{6}$/i),
    });
    expect(audit.ratios.ink).toBeGreaterThanOrEqual(4.5);
    expect(audit.ratios.mutedOnWhite).toBeGreaterThanOrEqual(4.5);
    expect(audit.ratios.mutedOnCanvas).toBeGreaterThanOrEqual(4.5);
    expect(audit.ratios.brand).toBeGreaterThanOrEqual(4.5);
    expect(audit.ratios.coral).toBeGreaterThanOrEqual(4.5);
    expect(audit.landmarks.main).toBe(1);
    expect(audit.landmarks.navigation).toBeGreaterThan(0);
});

test('keeps shared keyboard focus paths usable', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    await page.keyboard.press('Tab');
    await expect(page.locator('a[href="#main-content"]')).toBeFocused();
    await expect(page.locator('a[href="#main-content"]')).toHaveCSS('outline-style', 'solid');
    await expect(page.locator('a[href="#main-content"]')).toHaveCSS('outline-width', '3px');
    await page.keyboard.press('Enter');
    await expect(page.locator('main#main-content')).toBeFocused();

    const searchTrigger = page.getByRole('button', { name: 'Search workspace' });
    await searchTrigger.click();
    const searchInput = page.getByRole('textbox', { name: 'Search workspace' });
    await expect(searchInput).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Close search' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(searchTrigger).toBeFocused();

    const accountTrigger = page.getByRole('button', { name: 'Open account menu' });
    await accountTrigger.press('ArrowDown');
    await expect(page.getByRole('menuitem').first()).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(accountTrigger).toBeFocused();

    let releaseCustomersRequest = () => {};
    const customersRequestGate = new Promise<void>((resolve) => {
        releaseCustomersRequest = resolve;
    });
    await page.route('**/customers', async (route) => {
        const response = await route.fetch();
        await customersRequestGate;
        await route.fulfill({ response });
    });
    const customerVisit = page.getByRole('link', { name: 'Customers' }).first().click();
    await expect(page.locator('[role="status"][aria-live="polite"]')).toHaveText('Loading page…');
    await expect(page.locator('main#main-content')).toHaveAttribute('aria-busy', 'true');
    releaseCustomersRequest();
    await customerVisit;
    await expect(page.locator('main#main-content')).toHaveAttribute('aria-busy', 'false');
    await page.unroute('**/customers');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/dashboard');
    const mobileNavTrigger = page.locator('button[aria-controls="mobile-navigation"]');
    await mobileNavTrigger.click();
    const mobileNavigation = page.locator('#mobile-navigation');
    await expect(mobileNavigation).toHaveAttribute('role', 'dialog');
    await expect(mobileNavigation).toHaveAttribute('aria-modal', 'true');
    const mobileClose = mobileNavigation.getByRole('button', { name: 'Close navigation' });
    await expect(mobileClose).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(
        mobileNavigation.locator('a[href], button:not([disabled])').last(),
    ).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(mobileNavTrigger).toBeFocused();
});

test('keeps high-use workspace pages within a phone viewport', async ({ page }) => {
    test.setTimeout(120_000);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await Promise.all([
        page.waitForURL(/\/(dashboard|customers|profile)$/),
        page.getByRole('button', { name: 'Enter workspace' }).click(),
    ]);

    for (const path of ['/dashboard', '/customers', '/services', '/billing/invoices', '/billing/payments', '/operations/work-orders', '/operations/inventory', '/settings/general']) {
        await page.goto(path);
        await expect(page.locator('main#main-content:visible'), `${path} should fit a phone viewport`).toBeVisible();

        const dimensions = await page.evaluate(() => ({
            body: document.body.scrollWidth,
            document: document.documentElement.scrollWidth,
            viewport: window.innerWidth,
        }));

        expect(dimensions.body, `${path} creates horizontal page overflow`).toBeLessThanOrEqual(dimensions.viewport + 1);
        expect(dimensions.document, `${path} creates horizontal document overflow`).toBeLessThanOrEqual(
            dimensions.viewport + 1,
        );
    }
});
