import { expect, test } from '@playwright/test';

const email = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const password = process.env.E2E_ADMIN_PASSWORD ?? 'password';

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
        '/customers',
        '/customers/create',
        '/settings/general',
        '/settings/notification-templates',
        '/operations/inventory',
        '/operations/optical',
        '/operations/work-orders/calendar',
        '/billing/invoices',
        '/billing/payments',
        '/reports/operations',
        '/partners/commercial',
        '/notifications',
        '/field',
    ]) {
        await page.goto(path);

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
    }
});
