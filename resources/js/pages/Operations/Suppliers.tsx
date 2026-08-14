import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, Edit3, FileText, Plus, Receipt, Save, Store, X } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Contract = {
    id: number;
    service_type: string;
    wholesale_currency: string;
    effective_from: string;
    effective_to: string | null;
    status: string;
};

type Bill = {
    id: number;
    reference: string;
    period_start: string;
    period_end: string;
    amount: number;
    currency: string;
    paid_amount: number;
    status: string;
};

type Supplier = {
    id: number;
    name: string;
    code: string;
    contact_email: string | null;
    is_active: boolean;
    contracts: Contract[];
    bills: Bill[];
};

type Props = PageProps & {
    suppliers: Supplier[];
    canManage: boolean;
    currencies: CurrencyOption[];
};

function fieldA11y(id: string, error?: string) {
    return {
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    };
}

function FieldError({ id, message, t }: { id: string; message?: string; t: (key: string) => string }) {
    return message ? (
        <p id={`${id}-error`} className="field-error" role="alert">
            {t(message)}
        </p>
    ) : null;
}

function BillPaymentForm({ bill, onDone }: { bill: Bill; onDone: () => void }) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const fieldId = (name: string) => `supplier-bill-${bill.id}-${name}`;
    const form = useForm({
        amount: '',
        paid_at: new Date().toISOString().slice(0, 10),
        method: 'bank_transfer',
        reference: '',
    });
    const remaining = bill.amount - bill.paid_amount;

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/operations/supplier-bills/${bill.id}/payments`, {
            onSuccess: () => {
                form.reset('amount', 'reference');
                onDone();
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 grid gap-3 rounded-lg bg-sand/50 p-4 sm:grid-cols-4">
            <label>
                <span className="field-label">{t('Amount remaining')}</span>
                <input className="field" value={remaining} readOnly aria-label={t('Amount remaining')} />
            </label>
            <label>
                <span className="field-label">{t('Payment amount')}</span>
                <input
                    id={fieldId('amount')}
                    className="field"
                    type="number"
                    min="1"
                    max={remaining}
                    {...fieldA11y(fieldId('amount'), form.errors.amount)}
                    value={form.data.amount}
                    onChange={(event) => form.setData('amount', event.target.value)}
                    required
                />
                <FieldError id={fieldId('amount')} message={form.errors.amount} t={t} />
            </label>
            <label>
                <span className="field-label">{t('Method')}</span>
                <ResponsiveSelect
                    id={fieldId('method')}
                    className="field"
                    {...fieldA11y(fieldId('method'), form.errors.method)}
                    value={form.data.method}
                    onChange={(event) => form.setData('method', event.target.value)}
                >
                    <option value="bank_transfer">{t('Bank transfer')}</option>
                    <option value="cash">{t('Cash')}</option>
                    <option value="card">{t('Card')}</option>
                    <option value="other">{t('Other')}</option>
                </ResponsiveSelect>
                <FieldError id={fieldId('method')} message={form.errors.method} t={t} />
            </label>
            <label>
                <span className="field-label">{t('Paid on')}</span>
                <input
                    id={fieldId('paid-at')}
                    className="field"
                    type="date"
                    {...fieldA11y(fieldId('paid-at'), form.errors.paid_at)}
                    value={form.data.paid_at}
                    onChange={(event) => form.setData('paid_at', event.target.value)}
                    required
                />
                <FieldError id={fieldId('paid-at')} message={form.errors.paid_at} t={t} />
            </label>
            <label className="sm:col-span-3">
                <span className="field-label">{t('Payment reference')} ({t('optional')})</span>
                <input
                    id={fieldId('reference')}
                    className="field"
                    {...fieldA11y(fieldId('reference'), form.errors.reference)}
                    value={form.data.reference}
                    onChange={(event) => form.setData('reference', event.target.value)}
                    placeholder={t('TRX-2026-08-001')}
                />
                <FieldError id={fieldId('reference')} message={form.errors.reference} t={t} />
            </label>
            <div className="flex items-end justify-end">
                <button type="submit" className="button-primary" disabled={form.processing || remaining <= 0}>
                    <Save size={15} /> {t('Record payment')}
                </button>
            </div>
        </form>
    );
}

function SupplierCard({
    supplier,
    currencies,
    canManage,
}: {
    supplier: Supplier;
    currencies: CurrencyOption[];
    canManage: boolean;
}) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const fieldId = (name: string) => `supplier-${supplier.id}-${name}`;
    const [contractOpen, setContractOpen] = useState(false);
    const [editingContractId, setEditingContractId] = useState<number | null>(null);
    const [billOpen, setBillOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [paymentBillId, setPaymentBillId] = useState<number | null>(null);
    const editForm = useForm({
        name: supplier.name,
        code: supplier.code,
        contact_email: supplier.contact_email ?? '',
        is_active: supplier.is_active,
    });
    const contractForm = useForm({
        service_type: 'upstream_credential',
        wholesale_currency: currencies[0]?.code ?? 'USD',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        status: 'active',
    });
    const contractEditForm = useForm({
        service_type: '',
        wholesale_currency: currencies[0]?.code ?? 'USD',
        effective_from: '',
        effective_to: '',
        status: 'active',
    });
    const billForm = useForm({
        reference: '',
        period_start: new Date().toISOString().slice(0, 10),
        period_end: new Date().toISOString().slice(0, 10),
        amount: '',
        currency: currencies[0]?.code ?? 'USD',
        notes: '',
    });

    const submitContract = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        contractForm.post(`/operations/suppliers/${supplier.id}/contracts`, {
            onSuccess: () => {
                contractForm.reset();
                setContractOpen(false);
            },
        });
    };

    const submitBill = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        billForm.post(`/operations/suppliers/${supplier.id}/bills`, {
            onSuccess: () => {
                billForm.reset();
                setBillOpen(false);
            },
        });
    };

    const startEdit = () => {
        editForm.setData({
            name: supplier.name,
            code: supplier.code,
            contact_email: supplier.contact_email ?? '',
            is_active: supplier.is_active,
        });
        editForm.clearErrors();
        setEditOpen(true);
    };

    const cancelEdit = () => {
        setEditOpen(false);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitEdit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editForm.patch(`/operations/suppliers/${supplier.id}`, { onSuccess: () => setEditOpen(false) });
    };

    const startContractEdit = (contract: Contract) => {
        setEditingContractId(contract.id);
        contractEditForm.setData({
            service_type: contract.service_type,
            wholesale_currency: contract.wholesale_currency,
            effective_from: contract.effective_from,
            effective_to: contract.effective_to ?? '',
            status: contract.status,
        });
        contractEditForm.clearErrors();
    };

    const cancelContractEdit = () => {
        setEditingContractId(null);
        contractEditForm.reset();
        contractEditForm.clearErrors();
    };

    const saveContract = (contract: Contract) => {
        contractEditForm.patch(`/operations/supplier-contracts/${contract.id}`, {
            onSuccess: cancelContractEdit,
        });
    };

    return (
        <div className="card p-6">
            <div className="flex flex-col justify-between gap-3 border-b border-line pb-5 sm:flex-row sm:items-start">
                <div className="flex items-start gap-3">
                    <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                        <Store size={18} />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold">{supplier.name}</h2>
                        <p className="mt-1 text-sm text-muted">
                            {supplier.code} {supplier.contact_email && `· ${supplier.contact_email}`}
                        </p>
                    </div>
                </div>
                {canManage && (
                    <div className="flex gap-2">
                        <button type="button" className="button-quiet" onClick={startEdit}>
                            <Edit3 size={15} /> {t('Edit')}
                        </button>
                        <button type="button" className="button-quiet" onClick={() => setContractOpen((open) => !open)}>
                            <FileText size={15} /> {t('Contract')}
                        </button>
                        <button type="button" className="button-quiet" onClick={() => setBillOpen((open) => !open)}>
                            <Receipt size={15} /> {t('Bill')}
                        </button>
                    </div>
                )}
            </div>

            <div className="mt-3 flex items-center gap-2 text-xs font-semibold">
                <span className={supplier.is_active ? 'text-brand' : 'text-muted'}>
                    {supplier.is_active ? t('Active supplier') : t('Inactive supplier')}
                </span>
                {!supplier.is_active && (
                    <span className="text-muted">{t('New receiving and billing should be reviewed.')}</span>
                )}
            </div>

            {editOpen && (
                <form
                    onSubmit={submitEdit}
                    className="mt-5 grid gap-4 rounded-lg bg-sand/50 p-4 md:grid-cols-2 xl:grid-cols-5"
                >
                    <label>
                        <span className="field-label">{t('Supplier name')}</span>
                        <input
                            id={fieldId('edit-name')}
                            className="field"
                            {...fieldA11y(fieldId('edit-name'), editForm.errors.name)}
                            value={editForm.data.name}
                            onChange={(event) => editForm.setData('name', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('edit-name')} message={editForm.errors.name} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Code')}</span>
                        <input
                            id={fieldId('edit-code')}
                            className="field uppercase"
                            {...fieldA11y(fieldId('edit-code'), editForm.errors.code)}
                            value={editForm.data.code}
                            onChange={(event) => editForm.setData('code', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('edit-code')} message={editForm.errors.code} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Contact email')}</span>
                        <input
                            id={fieldId('edit-contact-email')}
                            className="field"
                            type="email"
                            {...fieldA11y(fieldId('edit-contact-email'), editForm.errors.contact_email)}
                            value={editForm.data.contact_email}
                            onChange={(event) => editForm.setData('contact_email', event.target.value)}
                        />
                        <FieldError id={fieldId('edit-contact-email')} message={editForm.errors.contact_email} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Status')}</span>
                        <ResponsiveSelect
                            id={fieldId('edit-status')}
                            className="field"
                            {...fieldA11y(fieldId('edit-status'), editForm.errors.is_active)}
                            value={editForm.data.is_active ? 'active' : 'inactive'}
                            onChange={(event) => editForm.setData('is_active', event.target.value === 'active')}
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </ResponsiveSelect>
                        <FieldError id={fieldId('edit-status')} message={editForm.errors.is_active} t={t} />
                    </label>
                    <div className="flex items-end gap-2">
                        <button type="submit" className="button-primary" disabled={editForm.processing}>
                            <Save size={15} /> {t('Save changes')}
                        </button>
                        <button
                            type="button"
                            className="button-quiet"
                            disabled={editForm.processing}
                            onClick={cancelEdit}
                        >
                            <X size={15} /> {t('Cancel')}
                        </button>
                    </div>
                </form>
            )}

            {contractOpen && (
                <form
                    onSubmit={submitContract}
                    className="mt-5 grid gap-4 rounded-lg bg-sand/50 p-4 md:grid-cols-2 xl:grid-cols-5"
                >
                    <label>
                        <span className="field-label">{t('Service type')}</span>
                        <input
                            id={fieldId('contract-service-type')}
                            className="field"
                            {...fieldA11y(fieldId('contract-service-type'), contractForm.errors.service_type)}
                            value={contractForm.data.service_type}
                            onChange={(event) => contractForm.setData('service_type', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('contract-service-type')} message={contractForm.errors.service_type} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Wholesale currency')}</span>
                        <CurrencyCombobox
                            id={fieldId('contract-currency')}
                            className="field"
                            {...fieldA11y(fieldId('contract-currency'), contractForm.errors.wholesale_currency)}
                            value={contractForm.data.wholesale_currency}
                            currencies={currencies}
                            onChange={(value) => contractForm.setData('wholesale_currency', value)}
                        />
                        <FieldError id={fieldId('contract-currency')} message={contractForm.errors.wholesale_currency} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Effective from')}</span>
                        <input
                            id={fieldId('contract-effective-from')}
                            className="field"
                            type="date"
                            {...fieldA11y(fieldId('contract-effective-from'), contractForm.errors.effective_from)}
                            value={contractForm.data.effective_from}
                            onChange={(event) => contractForm.setData('effective_from', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('contract-effective-from')} message={contractForm.errors.effective_from} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Effective to')}</span>
                        <input
                            id={fieldId('contract-effective-to')}
                            className="field"
                            type="date"
                            {...fieldA11y(fieldId('contract-effective-to'), contractForm.errors.effective_to)}
                            value={contractForm.data.effective_to}
                            onChange={(event) => contractForm.setData('effective_to', event.target.value)}
                        />
                        <FieldError id={fieldId('contract-effective-to')} message={contractForm.errors.effective_to} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Status')}</span>
                        <ResponsiveSelect
                            id={fieldId('contract-status')}
                            className="field"
                            {...fieldA11y(fieldId('contract-status'), contractForm.errors.status)}
                            value={contractForm.data.status}
                            onChange={(event) => contractForm.setData('status', event.target.value)}
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="suspended">{t('Suspended')}</option>
                            <option value="expired">{t('Expired')}</option>
                        </ResponsiveSelect>
                        <FieldError id={fieldId('contract-status')} message={contractForm.errors.status} t={t} />
                    </label>
                    <div className="flex justify-end md:col-span-2 xl:col-span-5">
                        <button type="submit" className="button-primary" disabled={contractForm.processing}>
                            <Save size={15} /> {t('Save contract')}
                        </button>
                    </div>
                </form>
            )}

            {billOpen && (
                <form
                    onSubmit={submitBill}
                    className="mt-5 grid gap-4 rounded-lg bg-sand/50 p-4 md:grid-cols-2 xl:grid-cols-5"
                >
                    <label>
                        <span className="field-label">{t('Bill reference')}</span>
                        <input
                            id={fieldId('bill-reference')}
                            className="field"
                            {...fieldA11y(fieldId('bill-reference'), billForm.errors.reference)}
                            value={billForm.data.reference}
                            onChange={(event) => billForm.setData('reference', event.target.value)}
                            placeholder={t('INV-2026-08')}
                            required
                        />
                        <FieldError id={fieldId('bill-reference')} message={billForm.errors.reference} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Amount (minor units)')}</span>
                        <input
                            id={fieldId('bill-amount')}
                            className="field"
                            type="number"
                            min="1"
                            {...fieldA11y(fieldId('bill-amount'), billForm.errors.amount)}
                            value={billForm.data.amount}
                            onChange={(event) => billForm.setData('amount', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('bill-amount')} message={billForm.errors.amount} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Currency')}</span>
                        <CurrencyCombobox
                            id={fieldId('bill-currency')}
                            className="field"
                            {...fieldA11y(fieldId('bill-currency'), billForm.errors.currency)}
                            value={billForm.data.currency}
                            currencies={currencies}
                            onChange={(value) => billForm.setData('currency', value)}
                        />
                        <FieldError id={fieldId('bill-currency')} message={billForm.errors.currency} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Period from')}</span>
                        <input
                            id={fieldId('bill-period-start')}
                            className="field"
                            type="date"
                            {...fieldA11y(fieldId('bill-period-start'), billForm.errors.period_start)}
                            value={billForm.data.period_start}
                            onChange={(event) => billForm.setData('period_start', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('bill-period-start')} message={billForm.errors.period_start} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Period to')}</span>
                        <input
                            id={fieldId('bill-period-end')}
                            className="field"
                            type="date"
                            {...fieldA11y(fieldId('bill-period-end'), billForm.errors.period_end)}
                            value={billForm.data.period_end}
                            onChange={(event) => billForm.setData('period_end', event.target.value)}
                            required
                        />
                        <FieldError id={fieldId('bill-period-end')} message={billForm.errors.period_end} t={t} />
                    </label>
                    <label className="md:col-span-2 xl:col-span-4">
                        <span className="field-label">{t('Notes')}</span>
                        <input
                            id={fieldId('bill-notes')}
                            className="field"
                            {...fieldA11y(fieldId('bill-notes'), billForm.errors.notes)}
                            value={billForm.data.notes}
                            onChange={(event) => billForm.setData('notes', event.target.value)}
                        />
                        <FieldError id={fieldId('bill-notes')} message={billForm.errors.notes} t={t} />
                    </label>
                    <div className="flex items-end justify-end">
                        <button type="submit" className="button-primary" disabled={billForm.processing}>
                            <Save size={15} /> {t('Save bill')}
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-5 grid gap-6 lg:grid-cols-2">
                <section>
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold">{t('Contracts')}</h3>
                        <span className="text-xs text-muted">{supplier.contracts.length}</span>
                    </div>
                    <div className="mt-3 divide-y divide-line">
                        {supplier.contracts.map((contract) => (
                            <div key={contract.id} className="py-3 text-sm">
                                {editingContractId === contract.id ? (
                                    <div className="grid gap-3 rounded-lg bg-sand/50 p-4 md:grid-cols-2">
                                        <label>
                                        <span className="field-label">{t('Service type')}</span>
                                            <input
                                                id={fieldId('contract-edit-service-type')}
                                                className="field"
                                                {...fieldA11y(fieldId('contract-edit-service-type'), contractEditForm.errors.service_type)}
                                                value={contractEditForm.data.service_type}
                                                onChange={(event) =>
                                                    contractEditForm.setData('service_type', event.target.value)
                                                }
                                                required
                                            />
                                            <FieldError id={fieldId('contract-edit-service-type')} message={contractEditForm.errors.service_type} t={t} />
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Wholesale currency')}</span>
                                            <CurrencyCombobox
                                                id={fieldId('contract-edit-currency')}
                                                className="field"
                                                {...fieldA11y(fieldId('contract-edit-currency'), contractEditForm.errors.wholesale_currency)}
                                                value={contractEditForm.data.wholesale_currency}
                                                currencies={currencies}
                                                onChange={(value) =>
                                                    contractEditForm.setData('wholesale_currency', value)
                                                }
                                            />
                                            <FieldError id={fieldId('contract-edit-currency')} message={contractEditForm.errors.wholesale_currency} t={t} />
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Effective from')}</span>
                                            <input
                                                id={fieldId('contract-edit-effective-from')}
                                                className="field"
                                                type="date"
                                                {...fieldA11y(fieldId('contract-edit-effective-from'), contractEditForm.errors.effective_from)}
                                                value={contractEditForm.data.effective_from}
                                                onChange={(event) =>
                                                    contractEditForm.setData('effective_from', event.target.value)
                                                }
                                                required
                                            />
                                            <FieldError id={fieldId('contract-edit-effective-from')} message={contractEditForm.errors.effective_from} t={t} />
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Effective to')}</span>
                                            <input
                                                id={fieldId('contract-edit-effective-to')}
                                                className="field"
                                                type="date"
                                                {...fieldA11y(fieldId('contract-edit-effective-to'), contractEditForm.errors.effective_to)}
                                                value={contractEditForm.data.effective_to}
                                                onChange={(event) =>
                                                    contractEditForm.setData('effective_to', event.target.value)
                                                }
                                            />
                                            <FieldError id={fieldId('contract-edit-effective-to')} message={contractEditForm.errors.effective_to} t={t} />
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Status')}</span>
                                            <ResponsiveSelect
                                                id={fieldId('contract-edit-status')}
                                                className="field"
                                                {...fieldA11y(fieldId('contract-edit-status'), contractEditForm.errors.status)}
                                                value={contractEditForm.data.status}
                                                onChange={(event) =>
                                                    contractEditForm.setData('status', event.target.value)
                                                }
                                            >
                                                <option value="active">{t('Active')}</option>
                                                <option value="suspended">{t('Suspended')}</option>
                                                <option value="expired">{t('Expired')}</option>
                                            </ResponsiveSelect>
                                            <FieldError id={fieldId('contract-edit-status')} message={contractEditForm.errors.status} t={t} />
                                        </label>
                                        <div className="flex items-end gap-2">
                                            <button
                                                type="button"
                                                className="button-secondary"
                                                disabled={contractEditForm.processing}
                                                onClick={() => saveContract(contract)}
                                            >
                                                <Save size={14} /> {t('Save contract')}
                                            </button>
                                            <button
                                                type="button"
                                                className="button-quiet"
                                                disabled={contractEditForm.processing}
                                                onClick={cancelContractEdit}
                                            >
                                                <X size={14} /> {t('Cancel')}
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="font-semibold">{contract.service_type}</span>
                                            <div className="flex items-center gap-2">
                                                <span className="status-badge">{enumLabel(contract.status, t)}</span>
                                                {canManage && (
                                                    <button
                                                        type="button"
                                                        className="text-xs font-semibold text-brand hover:underline"
                                                        onClick={() => startContractEdit(contract)}
                                                    >
                                                        {t('Edit')}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        <p className="mt-1 text-xs text-muted">
                                            {contract.wholesale_currency} · {contract.effective_from}{' '}
                                            {contract.effective_to && `→ ${contract.effective_to}`}
                                        </p>
                                    </>
                                )}
                            </div>
                        ))}
                        {supplier.contracts.length === 0 && (
                            <p className="py-3 text-sm text-muted">{t('No contracts recorded.')}</p>
                        )}
                    </div>
                </section>
                <section>
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold">{t('Bills')}</h3>
                        <span className="text-xs text-muted">{supplier.bills.length}</span>
                    </div>
                    <div className="mt-3 divide-y divide-line">
                        {supplier.bills.map((bill) => {
                            const paymentOpen = paymentBillId === bill.id;
                            return (
                                <div key={bill.id} className="py-3 text-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">{bill.reference}</p>
                                            <p className="mt-1 text-xs text-muted">
                                                {bill.period_start} → {bill.period_end}
                                            </p>
                                        </div>
                                        <div className="text-end">
                                            <p className="font-semibold">{formatMoney(bill.amount, bill.currency)}</p>
                                            <p className="mt-1 text-xs text-muted">
                                {t('Paid')} {formatMoney(bill.paid_amount, bill.currency)} · {enumLabel(bill.status, t)}
                                            </p>
                                        </div>
                                    </div>
                                    {canManage && bill.status !== 'paid' && (
                                        <button
                                            type="button"
                                            className="mt-2 text-xs font-semibold text-brand hover:underline"
                                            onClick={() => setPaymentBillId(paymentOpen ? null : bill.id)}
                                        >
                                            {paymentOpen ? t('Close payment form') : t('Record payment')}
                                        </button>
                                    )}
                                    {paymentOpen && (
                                        <BillPaymentForm bill={bill} onDone={() => setPaymentBillId(null)} />
                                    )}
                                </div>
                            );
                        })}
                        {supplier.bills.length === 0 && (
                            <p className="py-3 text-sm text-muted">{t('No bills recorded.')}</p>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}

export default function SuppliersPage({ suppliers, canManage, currencies }: Props) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const supplierForm = useForm({ name: '', code: '', contact_email: '' });

    const submitSupplier = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        supplierForm.post('/operations/suppliers', { onSuccess: () => supplierForm.reset() });
    };

    return (
        <AppLayout>
            <Head title={t('Suppliers')} />
            <Link
                href="/operations/credentials"
                className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ChevronLeft size={16} /> {t('Back to credentials')}
            </Link>
            <div>
                <p className="eyebrow">{t('Supplier operations')}</p>
                <h1 className="page-title">{t('Suppliers, contracts and bills')}</h1>
                <p className="page-subtitle">
                    {t('Keep upstream commercial records alongside credential inventory and reconciliation.')}
                </p>
            </div>
            {canManage && (
                <form onSubmit={submitSupplier} className="card mt-8 grid gap-4 p-5 md:grid-cols-4">
                    <label>
                        <span className="field-label">{t('Supplier name')}</span>
                        <input
                            id="supplier-create-name"
                            className="field"
                            {...fieldA11y('supplier-create-name', supplierForm.errors.name)}
                            value={supplierForm.data.name}
                            onChange={(event) => supplierForm.setData('name', event.target.value)}
                            placeholder={t('Transit ISP')}
                            required
                        />
                        <FieldError id="supplier-create-name" message={supplierForm.errors.name} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Code')}</span>
                        <input
                            id="supplier-create-code"
                            className="field uppercase"
                            {...fieldA11y('supplier-create-code', supplierForm.errors.code)}
                            value={supplierForm.data.code}
                            onChange={(event) => supplierForm.setData('code', event.target.value)}
                            placeholder={t('TRANSIT')}
                            required
                        />
                        <FieldError id="supplier-create-code" message={supplierForm.errors.code} t={t} />
                    </label>
                    <label>
                        <span className="field-label">{t('Contact email')}</span>
                        <input
                            id="supplier-create-contact-email"
                            className="field"
                            type="email"
                            {...fieldA11y('supplier-create-contact-email', supplierForm.errors.contact_email)}
                            value={supplierForm.data.contact_email}
                            onChange={(event) => supplierForm.setData('contact_email', event.target.value)}
                            placeholder={t('billing@example.com')}
                        />
                        <FieldError id="supplier-create-contact-email" message={supplierForm.errors.contact_email} t={t} />
                    </label>
                    <div className="flex items-end justify-end">
                        <button type="submit" className="button-primary" disabled={supplierForm.processing}>
                            <Plus size={16} /> {t('Add supplier')}
                        </button>
                    </div>
                </form>
            )}
            <div className="mt-8 space-y-5">
                {suppliers.map((supplier) => (
                    <SupplierCard key={supplier.id} supplier={supplier} currencies={currencies} canManage={canManage} />
                ))}
                {suppliers.length === 0 && (
                    <div className="card p-8 text-center text-sm text-muted">{t('No suppliers recorded yet.')}</div>
                )}
            </div>
        </AppLayout>
    );
}
