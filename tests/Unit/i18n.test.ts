import assert from 'node:assert/strict';
import test from 'node:test';

import { createTranslator } from '../../resources/js/lib/i18n.ts';

test('preserves literal English copy and complete sentences', () => {
    const translate = createTranslator('en');

    assert.equal(translate('Collections and revenue'), 'Collections and revenue');
    assert.equal(translate('Your request was saved. Please try again.'), 'Your request was saved. Please try again.');
    assert.equal(translate('unknown.screen.load_error'), 'Load Error');
});

test('explains payment confirmation in every client language', () => {
    for (const [locale, confirmation] of [
        ['en', /confirm/i],
        ['ar', /تأكيد/],
        ['fr', /confirmation/],
    ] as const) {
        const translate = createTranslator(locale);
        const submitted = translate('portal.dashboard.payment_submitted');

        assert.notEqual(submitted, 'Payment Submitted');
        assert.match(submitted, confirmation);
    }
    assert.match(createTranslator('en')('portal.dashboard.payment_submitted'), /confirm/i);
});

test('explains how clients enter the English portal', () => {
    const translate = createTranslator('en');

    assert.equal(translate('portal.manage_connection'), 'Manage your connection.');
    assert.match(translate('portal.subtitle'), /phone.*code/i);
    assert.match(translate('portal.sign_in_error'), /try again/i);
});
