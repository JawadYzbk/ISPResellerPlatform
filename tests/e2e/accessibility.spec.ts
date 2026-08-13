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
    await expect(page.locator('main h1:visible'), `${path} should expose a page heading`).toHaveCount(1);
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

    for (const path of [
        '/dashboard',
        '/customers',
        '/customers/create',
        '/plans',
        '/services',
        '/settings/general',
        '/settings/locations',
        '/settings/readiness',
        '/settings/notification-templates',
        '/settings/ticket-responses',
        '/settings/users',
        '/settings/whatsapp',
        '/operations/inventory',
        '/operations/optical',
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
    ]) {
        await auditPage(page, path);
    }
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
    await page.keyboard.press('Enter');
    await expect(page.locator('main#main-content')).toBeFocused();

    const searchTrigger = page.getByRole('button', { name: 'Search workspace' });
    await searchTrigger.click();
    const searchInput = page.getByRole('textbox', { name: 'Search workspace' });
    await expect(searchInput).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(searchInput).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(searchTrigger).toBeFocused();

    const accountTrigger = page.getByRole('button', { name: 'Open account menu' });
    await accountTrigger.press('ArrowDown');
    await expect(page.getByRole('menuitem').first()).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(accountTrigger).toBeFocused();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/dashboard');
    const mobileNavTrigger = page.locator('button[aria-controls="mobile-navigation"]');
    await mobileNavTrigger.click();
    const mobileNavigation = page.locator('#mobile-navigation');
    const mobileClose = mobileNavigation.getByRole('button', { name: 'Close navigation' });
    await expect(mobileClose).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(
        mobileNavigation.locator('a[href], button:not([disabled])').last(),
    ).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(mobileNavTrigger).toBeFocused();
});
