import { expect, test, type Page, type Route } from '@playwright/test';

const tenantSlug = 'northline';
const apiRoot = `/api/v1/portal/${tenantSlug}`;
const sessionToken = 'mock-portal-token';

const customer = {
    public_id: 'customer-100',
    code: 'N-100',
    first_name: 'Ada',
    last_name: 'Lovelace',
    phone: '+96170123456',
    email: 'ada@example.test',
    address: '1 Main Street',
    latitude: null,
    longitude: null,
    documents: [],
    status: 'active',
    balance_amount: 45,
    balance_currency: 'USD',
    notification_preferences: null,
    zone: null,
    services: [],
    invoices: [],
    payments: [],
    tickets: [],
};

const currentInvoice = {
    id: 'invoice-current',
    number: 'INV-2026-001',
    status: 'issued',
    currency: 'USD',
    total_amount: 40,
    allocated_amount: 0,
    credited_amount: 0,
    outstanding_amount: 40,
    due_at: '2026-10-10T00:00:00Z',
    issued_at: '2026-09-30T00:00:00Z',
    lines: [{ description: 'Current monthly service', amount: 40, currency: 'USD' }],
};

const olderInvoice = {
    id: 'invoice-older',
    number: 'INV-2026-000',
    status: 'issued',
    currency: 'USD',
    total_amount: 30,
    allocated_amount: 0,
    credited_amount: 0,
    outstanding_amount: 30,
    due_at: '2026-09-10T00:00:00Z',
    issued_at: '2026-08-31T00:00:00Z',
    lines: [{ description: 'Older monthly service', amount: 30, currency: 'USD' }],
};

type RecordedRequest = {
    path: string;
    url: string;
    method: string;
    authorization: string | undefined;
    idempotencyKey: string | undefined;
};

type MockOptions = {
    customerFailures?: number;
    noticeFailures?: number;
    paymentIntentFailures?: number;
};

async function fulfillJson(route: Route, body: unknown, status = 200): Promise<void> {
    await route.fulfill({
        status,
        contentType: 'application/json',
        body: JSON.stringify(body),
    });
}

async function installPortalMocks(page: Page, options: MockOptions = {}): Promise<RecordedRequest[]> {
    await page.addInitScript((key) => sessionStorage.setItem(key, 'mock-portal-token'), `portal_token:${tenantSlug}`);

    const requests: RecordedRequest[] = [];
    let customerAttempts = 0;
    let noticeAttempts = 0;
    let paymentIntentAttempts = 0;

    await page.route(`**${apiRoot}/**`, async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname;
        const method = request.method();
        const headers = request.headers();

        requests.push({
            path,
            url: request.url(),
            method,
            authorization: headers.authorization,
            idempotencyKey: headers['x-idempotency-key'],
        });

        if (path === `${apiRoot}/me` && method === 'GET') {
            customerAttempts += 1;
            if (customerAttempts <= (options.customerFailures ?? 0)) {
                await fulfillJson(route, { message: 'Temporary server failure' }, 500);
                return;
            }

            await fulfillJson(route, customer);
            return;
        }

        if (path === `${apiRoot}/me/balance`) {
            await fulfillJson(route, {
                balance: { amount: 45, currency: 'USD' },
                next_due: {
                    invoice_id: currentInvoice.id,
                    number: currentInvoice.number,
                    amount: currentInvoice.outstanding_amount,
                    currency: currentInvoice.currency,
                    due_at: currentInvoice.due_at,
                },
            });
            return;
        }

        if (path === `${apiRoot}/billing`) {
            await fulfillJson(route, {
                invoices: [],
                payments: [],
                online_payments: { enabled: true, provider: 'stripe' },
            });
            return;
        }

        if (path === `${apiRoot}/me/notices`) {
            noticeAttempts += 1;
            if (noticeAttempts <= (options.noticeFailures ?? 0)) {
                await fulfillJson(route, { message: 'Temporary server failure' }, 500);
                return;
            }

            await fulfillJson(route, { data: [] });
            return;
        }

        if (path === `${apiRoot}/me/tickets`) {
            await fulfillJson(route, { data: [] });
            return;
        }

        if (path === `${apiRoot}/me/invoices`) {
            const cursor = url.searchParams.get('cursor');
            await fulfillJson(
                route,
                cursor === 'older-page'
                    ? { data: [olderInvoice], meta: { next_cursor: null, prev_cursor: 'current-page', per_page: 25 } }
                    : { data: [currentInvoice], meta: { next_cursor: 'older-page', prev_cursor: null, per_page: 25 } },
            );
            return;
        }

        if (path === `${apiRoot}/me/payments`) {
            await fulfillJson(route, { data: [], meta: { next_cursor: null, prev_cursor: null, per_page: 25 } });
            return;
        }

        if (path === `${apiRoot}/payments/intent` && method === 'POST') {
            paymentIntentAttempts += 1;
            if (paymentIntentAttempts <= (options.paymentIntentFailures ?? 0)) {
                if (paymentIntentAttempts === 1) {
                    await route.abort('failed');
                } else {
                    await route.fulfill({ status: 503, contentType: 'text/plain', body: 'Temporary upstream failure' });
                }
                return;
            }

            await fulfillJson(route, { payload: {} });
            return;
        }

        const invoiceResourcePrefix = `${apiRoot}/me/invoices/`;
        if (path.startsWith(invoiceResourcePrefix) && path.endsWith('/pdf')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/pdf',
                body: Buffer.from('%PDF-1.4 mock invoice'),
            });
            return;
        }

        if (path.startsWith(invoiceResourcePrefix)) {
            const invoiceId = path.slice(invoiceResourcePrefix.length);
            const invoice = invoiceId === olderInvoice.id ? olderInvoice : currentInvoice;
            await fulfillJson(route, {
                ...invoice,
                subtotal_amount: invoice.total_amount,
                tax_amount: 0,
                payments: [],
            });
            return;
        }

        await fulfillJson(route, { message: 'Unexpected mocked API request' }, 404);
    });

    return requests;
}

test.describe('customer portal recovery and billing history', () => {
    test('retries portal sign in after the code request loses its network connection', async ({ page }) => {
        let attempts = 0;
        await page.route(`**${apiRoot}/otp/request`, async (route) => {
            attempts += 1;
            if (attempts === 1) {
                await route.abort('failed');
                return;
            }

            await fulfillJson(route, { challenge_id: 123 });
        });
        await page.goto(`/portal/${tenantSlug}`);

        await expect(page.getByRole('heading', { name: 'Manage your connection.' })).toBeVisible();
        await page.getByLabel('Phone number').fill('+96170123456');
        const sendCodeButton = page.getByRole('button', { name: 'Send code' });
        await sendCodeButton.click();
        await expect(page.getByRole('alert')).toHaveText('We could not send your code. Please try again.');
        await expect(sendCodeButton).toBeEnabled();

        await sendCodeButton.click();
        await expect(page.getByLabel('Verification code')).toBeVisible();
    });

    test('keeps the dashboard open after server errors and retries each section independently', async ({ page }) => {
        await installPortalMocks(page, { customerFailures: 1, noticeFailures: 1 });
        await page.goto(`/portal/${tenantSlug}/dashboard`);

        await expect(page).toHaveURL(new RegExp(`/portal/${tenantSlug}/dashboard$`));
        await expect(page.locator('main').getByRole('alert')).toContainText(
            'Unable to load the portal. Please try again.',
        );
        await page.locator('main').getByRole('button', { name: 'Try again' }).click();
        await expect(page.getByRole('heading', { name: 'Ada Lovelace' })).toBeVisible();

        const notices = page.locator('section[aria-labelledby="notices-heading"]');
        await expect(notices.getByRole('alert')).toContainText('Unable to load the portal. Please try again.');
        await notices.getByRole('button', { name: 'Try again' }).click();
        await expect(notices.getByRole('alert')).toHaveCount(0);
        await expect(page.getByText(currentInvoice.number, { exact: true })).toBeVisible();
    });

    test('loads older outstanding invoices for payment and uses the authenticated detail and PDF endpoints', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const requests = await installPortalMocks(page);
        await page.goto(`/portal/${tenantSlug}/dashboard`);

        await expect(page.getByText(currentInvoice.number, { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Load older' }).click();

        const olderRow = page.locator('div.py-4').filter({ hasText: olderInvoice.number }).first();
        await expect(olderRow.getByText(olderInvoice.number, { exact: true })).toBeVisible();
        await expect(olderRow).toContainText('Outstanding');
        await page.getByLabel('Invoice').selectOption(olderInvoice.id);
        await expect(page.getByLabel('Invoice')).toHaveValue(olderInvoice.id);

        await olderRow.getByRole('button', { name: 'View details' }).click();
        await expect(olderRow.getByText('Older monthly service')).toBeVisible();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            olderRow.getByRole('button', { name: 'Download PDF' }).click(),
        ]);
        expect(download.suggestedFilename()).toBe(`${olderInvoice.number}.pdf`);

        const detailRequest = requests.find((request) => request.path.endsWith(`/me/invoices/${olderInvoice.id}`));
        const pdfRequest = requests.find((request) => request.path.endsWith(`/me/invoices/${olderInvoice.id}/pdf`));
        expect(detailRequest?.authorization).toBe(`Bearer ${sessionToken}`);
        expect(pdfRequest?.authorization).toBe(`Bearer ${sessionToken}`);
        expect(pdfRequest?.url).not.toContain(sessionToken);
    });

    test('retains the payment idempotency key after ambiguous failures and clears busy state for retry', async ({
        page,
    }) => {
        const requests = await installPortalMocks(page, { paymentIntentFailures: 2 });
        await page.goto(`/portal/${tenantSlug}/dashboard`);

        const payment = page.locator('[aria-labelledby="online-payment-heading"]');
        const continueButton = payment.getByRole('button', { name: 'Continue to payment' });
        await continueButton.click();
        await expect(payment.getByRole('alert')).toContainText('Unable to start the payment. Please try again.');
        await expect(continueButton).toBeEnabled();

        await continueButton.click();
        await expect(payment.getByRole('alert')).toContainText('Unable to start the payment. Please try again.');
        await expect(continueButton).toBeEnabled();

        const intentRequests = requests.filter((request) => request.path === `${apiRoot}/payments/intent`);
        expect(intentRequests).toHaveLength(2);
        expect(intentRequests[0].idempotencyKey).toBeTruthy();
        expect(intentRequests[1].idempotencyKey).toBe(intentRequests[0].idempotencyKey);
    });
});
