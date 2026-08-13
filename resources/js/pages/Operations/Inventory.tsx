import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Edit3, Package, Save, Search, X } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';
import { createTranslator, enumLabel } from '@/lib/i18n';

type InventoryUnit = {
    id: number;
    serial_number: string;
    status: 'available' | 'assigned' | 'returned' | 'damaged';
    assigned_at: string | null;
    item: { sku: string; name: string; category: string } | null;
    warehouse: { code: string; name: string } | null;
    service: { public_id: string; username: string; customer_public_id: string | null; customer: string | null } | null;
};

type AssignableService = {
    public_id: string;
    username: string;
    customer: string | null;
};

type BulkBalance = {
    inventory_item_id: number;
    warehouse_id: number;
    sku: string | null;
    name: string | null;
    warehouse: string | null;
    quantity: string;
};

type BulkItem = { id: number; sku: string; name: string };
type BulkWarehouse = { id: number; code: string; name: string };
type CatalogItem = {
    id: number;
    sku: string;
    name: string;
    category: string;
    is_serialized: boolean;
    reorder_level: number;
    is_active: boolean;
};
type CatalogWarehouse = {
    id: number;
    code: string;
    name: string;
    type: 'warehouse' | 'van' | 'collector';
    assigned_user_id: number | null;
    assigned_user: { id: number; name: string } | null;
    is_active: boolean;
};
type FieldUser = { id: number; name: string; role: 'collector' | 'technician' };
type TransferRequest = {
    id: string;
    type: 'replenishment' | 'return';
    status: 'pending' | 'approved' | 'rejected';
    quantity: string;
    note: string | null;
    review_note: string | null;
    created_at: string | null;
    requester: { name: string; role: string } | null;
    item: { sku: string; name: string } | null;
    source: { code: string; name: string } | null;
    destination: { code: string; name: string } | null;
};
type StockCount = {
    id: string;
    status: 'pending' | 'posted' | 'rejected';
    note: string | null;
    review_note: string | null;
    counted_at: string | null;
    warehouse: { code: string; name: string } | null;
    counter: { name: string; role: string } | null;
    lines: { item: { sku: string; name: string } | null; expected: string; counted: string; variance: string }[];
};
type InventoryMovement = {
    id: string;
    movement_type: string;
    kind: 'serialized' | 'bulk';
    occurred_at: string | null;
    item: { sku: string; name: string } | null;
    serial_number: string | null;
    from_warehouse: string | null;
    to_warehouse: string | null;
    quantity: number | string;
    reference: string | null;
    actor: string | null;
    note: string | null;
};

type Props = PageProps & {
    units: Paginator<InventoryUnit>;
    filters: { status?: string; search?: string; movement_type?: string };
    canAssign?: boolean;
    canReceive?: boolean;
    canTransfer?: boolean;
    assignableServices?: AssignableService[];
    bulkBalances: BulkBalance[];
    bulkItems: BulkItem[];
    serializedItems: BulkItem[];
    catalogItems: CatalogItem[];
    bulkWarehouses: BulkWarehouse[];
    catalogWarehouses: CatalogWarehouse[];
    transferWarehouses: BulkWarehouse[];
    fieldUsers: FieldUser[];
    transferRequests: TransferRequest[];
    stockCounts: StockCount[];
    movements: InventoryMovement[];
};

export default function InventoryPage({
    units,
    filters,
    canAssign = false,
    canReceive = false,
    canTransfer = false,
    assignableServices = [],
    bulkBalances,
    bulkItems,
    serializedItems,
    catalogItems,
    bulkWarehouses,
    catalogWarehouses,
    transferWarehouses,
    fieldUsers,
    transferRequests,
    stockCounts,
    movements,
}: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const inventoryLabel = (value: string) => {
        const key = value === 'transfer_out' ? 'bulk_transfer_out' : value === 'transfer_in' ? 'bulk_transfer_in' : value;

        return t('inventory.' + key);
    };
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [movementType, setMovementType] = useState(filters.movement_type ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});
    const [selectedWarehouses, setSelectedWarehouses] = useState<Record<number, string>>({});
    const itemForm = useForm({ sku: '', name: '', category: '', is_serialized: false, reorder_level: '0' });
    const warehouseForm = useForm({ name: '', code: '', type: 'warehouse', assigned_user_id: '' });
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [editingWarehouseId, setEditingWarehouseId] = useState<number | null>(null);
    const itemEditForm = useForm({
        sku: '',
        name: '',
        category: '',
        is_serialized: false,
        reorder_level: '0',
        is_active: true,
    });
    const warehouseEditForm = useForm({
        name: '',
        code: '',
        type: 'warehouse',
        assigned_user_id: '',
        is_active: true,
    });
    const receiveForm = useForm({ inventory_item_id: '', warehouse_id: '', quantity: '', note: '' });
    const transferForm = useForm({
        inventory_item_id: '',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        quantity: '',
        note: '',
    });
    const unitForm = useForm({ inventory_item_id: '', warehouse_id: '', serial_number: '' });
    const reviewForm = useForm({ decision: 'approved', review_note: '' });
    const countReviewForm = useForm({ decision: 'posted', review_note: '' });

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/inventory',
            { search: search || undefined, status: status || undefined, movement_type: movementType || undefined },
            { preserveState: true, replace: true },
        );
    };

    const assignUnit = (unit: InventoryUnit) => {
        const servicePublicId = selectedServices[unit.id];
        if (!servicePublicId) {
            return;
        }

        router.post(`/operations/inventory/${unit.id}/assign`, { service_public_id: servicePublicId });
    };

    const submitReceive = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        receiveForm.post('/operations/inventory/bulk-receive', { onSuccess: () => receiveForm.reset() });
    };

    const submitBulkTransfer = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transferForm.post('/operations/inventory/bulk-transfer', { onSuccess: () => transferForm.reset() });
    };

    const submitItem = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        itemForm.post('/operations/inventory/items', { onSuccess: () => itemForm.reset() });
    };

    const submitWarehouse = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        warehouseForm.post('/operations/inventory/warehouses', { onSuccess: () => warehouseForm.reset() });
    };

    const submitUnit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        unitForm.post('/operations/inventory/serialized-receive', { onSuccess: () => unitForm.reset() });
    };

    const startItemEdit = (item: CatalogItem) => {
        setEditingItemId(item.id);
        itemEditForm.setData({
            sku: item.sku,
            name: item.name,
            category: item.category,
            is_serialized: item.is_serialized,
            reorder_level: String(item.reorder_level),
            is_active: item.is_active,
        });
        itemEditForm.clearErrors();
    };

    const cancelItemEdit = () => {
        setEditingItemId(null);
        itemEditForm.reset();
        itemEditForm.clearErrors();
    };

    const saveItem = (item: CatalogItem) => {
        itemEditForm.patch(`/operations/inventory/items/${item.id}`, { onSuccess: cancelItemEdit });
    };

    const startWarehouseEdit = (warehouse: CatalogWarehouse) => {
        setEditingWarehouseId(warehouse.id);
        warehouseEditForm.setData({
            name: warehouse.name,
            code: warehouse.code,
            type: warehouse.type,
            assigned_user_id: warehouse.assigned_user_id ? String(warehouse.assigned_user_id) : '',
            is_active: warehouse.is_active,
        });
        warehouseEditForm.clearErrors();
    };

    const cancelWarehouseEdit = () => {
        setEditingWarehouseId(null);
        warehouseEditForm.reset();
        warehouseEditForm.clearErrors();
    };

    const saveWarehouse = (warehouse: CatalogWarehouse) => {
        warehouseEditForm.patch(`/operations/inventory/warehouses/${warehouse.id}`, {
            onSuccess: cancelWarehouseEdit,
        });
    };

    const transferUnit = (unit: InventoryUnit) => {
        const warehouseId = selectedWarehouses[unit.id];
        if (warehouseId) router.post(`/operations/inventory/${unit.id}/transfer`, { warehouse_id: warehouseId });
    };

    const reviewTransferRequest = (request: TransferRequest, decision: 'approved' | 'rejected') => {
        reviewForm.transform(() => ({ decision, review_note: reviewForm.data.review_note }));
        reviewForm.patch(`/operations/inventory/requests/${request.id}`, {
            preserveScroll: true,
            onSuccess: () => reviewForm.reset(),
            onFinish: () => reviewForm.transform((data) => data),
        });
    };

    const reviewStockCount = (count: StockCount, decision: 'posted' | 'rejected') => {
        countReviewForm.transform(() => ({ decision, review_note: countReviewForm.data.review_note }));
        countReviewForm.patch(`/operations/inventory/stock-counts/${count.id}`, {
            preserveScroll: true,
            onSuccess: () => countReviewForm.reset(),
            onFinish: () => countReviewForm.transform((data) => data),
        });
    };

    return (
        <AppLayout>
            <Head title={t('inventory.title')} />
            <div>
                <p className="eyebrow">{t('inventory.eyebrow')}</p>
                <h1 className="page-title">{t('inventory.title')}</h1>
                <p className="page-subtitle">{t('inventory.subtitle')}</p>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">{t('inventory.search')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('inventory.search_placeholder')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('inventory.unit_status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('inventory.all_statuses')}</option>
                        <option value="available">{t('Available')}</option>
                        <option value="assigned">{t('Assigned')}</option>
                        <option value="returned">{t('Returned')}</option>
                        <option value="damaged">{t('Damaged')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('inventory.apply_filters')}
                </button>
            </form>

            {canReceive && (
                <section className="card mt-6 p-5">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                        <p className="section-title">{t('inventory.setup')}</p>
                            <p className="mt-1 text-sm text-muted">
                                {t('inventory.setup_description')}
                            </p>
                        </div>
                        <Package size={18} className="text-brand" />
                    </div>
                    <div className="mt-5 grid gap-6 xl:grid-cols-2">
                        <form
                            onSubmit={submitItem}
                            className="grid gap-3 rounded-xl border border-line bg-sand/30 p-4 sm:grid-cols-2"
                        >
                            <p className="text-sm font-semibold sm:col-span-2">{t('inventory.new_item')}</p>
                            <label>
                                <span className="field-label">{t('SKU')}</span>
                                <input
                                    className="field"
                                    value={itemForm.data.sku}
                                    onChange={(event) => itemForm.setData('sku', event.target.value)}
                                    placeholder="CABLE-UTP"
                                />
                                {itemForm.errors.sku && <p className="field-error">{itemForm.errors.sku}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('Name')}</span>
                                <input
                                    className="field"
                                    value={itemForm.data.name}
                                    onChange={(event) => itemForm.setData('name', event.target.value)}
                                    placeholder={t('Outdoor UTP cable')}
                                />
                                {itemForm.errors.name && <p className="field-error">{itemForm.errors.name}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('Category')}</span>
                                <input
                                    className="field"
                                    value={itemForm.data.category}
                                    onChange={(event) => itemForm.setData('category', event.target.value)}
                                    placeholder={t('cable')}
                                />
                                {itemForm.errors.category && <p className="field-error">{itemForm.errors.category}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('inventory.inventory_type')}</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={itemForm.data.is_serialized ? 'serialized' : 'bulk'}
                                    onChange={(event) =>
                                        itemForm.setData('is_serialized', event.target.value === 'serialized')
                                    }
                                >
                                    <option value="bulk">{t('inventory.bulk_quantity')}</option>
                                    <option value="serialized">{t('inventory.serialized_units')}</option>
                                </ResponsiveSelect>
                            </label>
                            <label>
                                <span className="field-label">{t('inventory.reorder_level')}</span>
                                <input
                                    className="field"
                                    type="number"
                                    min="0"
                                    value={itemForm.data.reorder_level}
                                    onChange={(event) => itemForm.setData('reorder_level', event.target.value)}
                                />
                                {itemForm.errors.reorder_level && (
                                    <p className="field-error">{itemForm.errors.reorder_level}</p>
                                )}
                            </label>
                            <button className="button-secondary sm:col-span-2" disabled={itemForm.processing}>
                                <Package size={15} /> {t('inventory.create_item')}
                            </button>
                        </form>
                        <form
                            onSubmit={submitWarehouse}
                            className="grid gap-3 rounded-xl border border-line bg-sand/30 p-4 sm:grid-cols-2"
                        >
                            <p className="text-sm font-semibold sm:col-span-2">{t('inventory.new_location')}</p>
                            <label>
                                <span className="field-label">{t('Name')}</span>
                                <input
                                    className="field"
                                    value={warehouseForm.data.name}
                                    onChange={(event) => warehouseForm.setData('name', event.target.value)}
                                    placeholder={t('Main warehouse')}
                                />
                                {warehouseForm.errors.name && (
                                    <p className="field-error">{warehouseForm.errors.name}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Code')}</span>
                                <input
                                    className="field uppercase"
                                    value={warehouseForm.data.code}
                                    onChange={(event) => warehouseForm.setData('code', event.target.value)}
                                    placeholder="MAIN"
                                />
                                {warehouseForm.errors.code && (
                                    <p className="field-error">{warehouseForm.errors.code}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('inventory.storage_type')}</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={warehouseForm.data.type}
                                    onChange={(event) =>
                                        warehouseForm.setData({
                                            ...warehouseForm.data,
                                            type: event.target.value,
                                            assigned_user_id:
                                                event.target.value === 'warehouse'
                                                    ? ''
                                                    : warehouseForm.data.assigned_user_id,
                                        })
                                    }
                                >
                                    <option value="warehouse">{t('inventory.warehouse')}</option>
                                    <option value="van">{t('inventory.technician_van')}</option>
                                    <option value="collector">{t('inventory.collector_stock')}</option>
                                </ResponsiveSelect>
                                {warehouseForm.errors.type && (
                                    <p className="field-error">{warehouseForm.errors.type}</p>
                                )}
                            </label>
                            {warehouseForm.data.type !== 'warehouse' && (
                                <label>
                                    <span className="field-label">{t('inventory.custodian')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={warehouseForm.data.assigned_user_id}
                                        onChange={(event) =>
                                            warehouseForm.setData('assigned_user_id', event.target.value)
                                        }
                                    >
                                        <option value="">{t('inventory.select_field_user')}</option>
                                        {fieldUsers.map((user) => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} · {user.role === 'collector' ? t('Collector') : t('Technician')}
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {warehouseForm.errors.assigned_user_id && (
                                        <p className="field-error">{warehouseForm.errors.assigned_user_id}</p>
                                    )}
                                </label>
                            )}
                            <div className="flex items-end sm:col-span-2">
                                <button className="button-secondary w-full" disabled={warehouseForm.processing}>
                                    <Package size={15} /> {t('inventory.create_location')}
                                </button>
                            </div>
                        </form>
                    </div>
                    {serializedItems.length > 0 && bulkWarehouses.length > 0 && (
                        <form
                            onSubmit={submitUnit}
                            className="mt-6 grid gap-3 border-t border-line pt-5 sm:grid-cols-3 sm:items-end"
                        >
                            <label>
                                <span className="field-label">{t('inventory.serialized_item')}</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={unitForm.data.inventory_item_id}
                                    onChange={(event) => unitForm.setData('inventory_item_id', event.target.value)}
                                >
                                    <option value="">{t('inventory.select_equipment')}</option>
                                    {serializedItems.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.sku} · {item.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                {unitForm.errors.inventory_item_id && (
                                    <p className="field-error">{unitForm.errors.inventory_item_id}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('inventory.warehouse')}</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={unitForm.data.warehouse_id}
                                    onChange={(event) => unitForm.setData('warehouse_id', event.target.value)}
                                >
                                    <option value="">{t('inventory.select_storage')}</option>
                                    {bulkWarehouses.map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>
                                            {warehouse.code} · {warehouse.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                {unitForm.errors.warehouse_id && (
                                    <p className="field-error">{unitForm.errors.warehouse_id}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('inventory.serial_number')}</span>
                                <input
                                    className="field"
                                    value={unitForm.data.serial_number}
                                    onChange={(event) => unitForm.setData('serial_number', event.target.value)}
                                    placeholder="CPE-ONU-0001"
                                />
                                {unitForm.errors.serial_number && (
                                    <p className="field-error">{unitForm.errors.serial_number}</p>
                                )}
                            </label>
                            <button className="button-secondary sm:col-span-3" disabled={unitForm.processing}>
                                <Package size={15} /> {t('inventory.receive_serialized')}
                            </button>
                        </form>
                    )}
                </section>
            )}

            {canReceive && (
                <section className="card mt-6 p-5">
                    <div>
                        <p className="section-title">{t('inventory.catalog')}</p>
                        <p className="mt-1 text-sm text-muted">
                            {t('inventory.catalog_description')}
                        </p>
                    </div>
                    <div className="mt-5 grid gap-6 xl:grid-cols-2">
                        <div className="rounded-xl border border-line">
                            <div className="border-b border-line px-4 py-3">
                                <p className="text-sm font-semibold">{t('inventory.items')}</p>
                            </div>
                            <div className="divide-y divide-line">
                                {catalogItems.map((item) => (
                                    <div key={item.id} className="px-4 py-4">
                                        {editingItemId === item.id ? (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <label>
                                                    <span className="field-label">{t('SKU')}</span>
                                                    <input
                                                        className="field"
                                                        value={itemEditForm.data.sku}
                                                        onChange={(event) =>
                                                            itemEditForm.setData('sku', event.target.value)
                                                        }
                                                    />
                                                    {itemEditForm.errors.sku && (
                                                        <p className="field-error">{itemEditForm.errors.sku}</p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Name')}</span>
                                                    <input
                                                        className="field"
                                                        value={itemEditForm.data.name}
                                                        onChange={(event) =>
                                                            itemEditForm.setData('name', event.target.value)
                                                        }
                                                    />
                                                    {itemEditForm.errors.name && (
                                                        <p className="field-error">{itemEditForm.errors.name}</p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Category')}</span>
                                                    <input
                                                        className="field"
                                                        value={itemEditForm.data.category}
                                                        onChange={(event) =>
                                                            itemEditForm.setData('category', event.target.value)
                                                        }
                                                    />
                                                    {itemEditForm.errors.category && (
                                                        <p className="field-error">{itemEditForm.errors.category}</p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Type')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={itemEditForm.data.is_serialized ? 'serialized' : 'bulk'}
                                                        onChange={(event) =>
                                                            itemEditForm.setData(
                                                                'is_serialized',
                                                                event.target.value === 'serialized',
                                                            )
                                                        }
                                                    >
                                                        <option value="bulk">{t('inventory.bulk_quantity')}</option>
                                                        <option value="serialized">{t('inventory.serialized_units')}</option>
                                                    </ResponsiveSelect>
                                                    {itemEditForm.errors.is_serialized && (
                                                        <p className="field-error">
                                                            {itemEditForm.errors.is_serialized}
                                                        </p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('inventory.reorder_level')}</span>
                                                    <input
                                                        className="field"
                                                        type="number"
                                                        min="0"
                                                        value={itemEditForm.data.reorder_level}
                                                        onChange={(event) =>
                                                            itemEditForm.setData('reorder_level', event.target.value)
                                                        }
                                                    />
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Status')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={itemEditForm.data.is_active ? 'active' : 'inactive'}
                                                        onChange={(event) =>
                                                            itemEditForm.setData(
                                                                'is_active',
                                                                event.target.value === 'active',
                                                            )
                                                        }
                                                    >
                                                        <option value="active">{t('Active')}</option>
                                                        <option value="inactive">{t('Inactive')}</option>
                                                    </ResponsiveSelect>
                                                </label>
                                                <div className="flex gap-2 sm:col-span-2">
                                                    <button
                                                        type="button"
                                                        className="button-secondary"
                                                        disabled={itemEditForm.processing}
                                                        onClick={() => saveItem(item)}
                                                    >
                                                        <Save size={14} /> {t('inventory.save_item')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="button-quiet"
                                                        disabled={itemEditForm.processing}
                                                        onClick={cancelItemEdit}
                                                    >
                                                        <X size={14} /> {t('Cancel')}
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">{item.name}</p>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {item.sku} · {item.category} ·{' '}
                                                        {item.is_serialized ? 'Serialized' : 'Bulk'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {t('inventory.reorder_at')} {item.reorder_level}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    <span
                                                        className={`text-xs font-semibold ${item.is_active ? 'text-brand' : 'text-muted'}`}
                                                    >
                                                        {item.is_active ? t('Active') : t('Inactive')}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="button-quiet px-2 py-2 text-xs"
                                                        onClick={() => startItemEdit(item)}
                                                    >
                                                        <Edit3 size={14} /> {t('Edit')}
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {catalogItems.length === 0 && (
                                    <p className="px-4 py-8 text-sm text-muted">{t('inventory.no_items')}</p>
                                )}
                            </div>
                        </div>

                        <div className="rounded-xl border border-line">
                            <div className="border-b border-line px-4 py-3">
                                <p className="text-sm font-semibold">{t('inventory.storage_locations')}</p>
                            </div>
                            <div className="divide-y divide-line">
                                {catalogWarehouses.map((warehouse) => (
                                    <div key={warehouse.id} className="px-4 py-4">
                                        {editingWarehouseId === warehouse.id ? (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <label>
                                                    <span className="field-label">{t('Name')}</span>
                                                    <input
                                                        className="field"
                                                        value={warehouseEditForm.data.name}
                                                        onChange={(event) =>
                                                            warehouseEditForm.setData('name', event.target.value)
                                                        }
                                                    />
                                                    {warehouseEditForm.errors.name && (
                                                        <p className="field-error">{warehouseEditForm.errors.name}</p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Code')}</span>
                                                    <input
                                                        className="field uppercase"
                                                        value={warehouseEditForm.data.code}
                                                        onChange={(event) =>
                                                            warehouseEditForm.setData('code', event.target.value)
                                                        }
                                                    />
                                                    {warehouseEditForm.errors.code && (
                                                        <p className="field-error">{warehouseEditForm.errors.code}</p>
                                                    )}
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('Type')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={warehouseEditForm.data.type}
                                                        onChange={(event) =>
                                                            warehouseEditForm.setData({
                                                                ...warehouseEditForm.data,
                                                                type: event.target.value,
                                                                assigned_user_id:
                                                                    event.target.value === 'warehouse'
                                                                        ? ''
                                                                        : warehouseEditForm.data.assigned_user_id,
                                                            })
                                                        }
                                                    >
                                                        <option value="warehouse">{t('inventory.warehouse')}</option>
                                                        <option value="van">{t('inventory.technician_van')}</option>
                                                        <option value="collector">{t('inventory.collector_stock')}</option>
                                                    </ResponsiveSelect>
                                                </label>
                                                {warehouseEditForm.data.type !== 'warehouse' && (
                                                    <label>
                                                        <span className="field-label">{t('inventory.custodian')}</span>
                                                        <ResponsiveSelect
                                                            className="field"
                                                            value={warehouseEditForm.data.assigned_user_id}
                                                            onChange={(event) =>
                                                                warehouseEditForm.setData(
                                                                    'assigned_user_id',
                                                                    event.target.value,
                                                                )
                                                            }
                                                        >
                                                            <option value="">{t('inventory.select_field_user')}</option>
                                                            {fieldUsers.map((user) => (
                                                                <option key={user.id} value={user.id}>
                                                                    {user.name} ·{' '}
                                                                    {user.role === 'collector'
                                                                        ? t('Collector')
                                                                        : t('Technician')}
                                                                </option>
                                                            ))}
                                                        </ResponsiveSelect>
                                                        {warehouseEditForm.errors.assigned_user_id && (
                                                            <p className="field-error">
                                                                {warehouseEditForm.errors.assigned_user_id}
                                                            </p>
                                                        )}
                                                    </label>
                                                )}
                                                <label>
                                                    <span className="field-label">{t('Status')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={warehouseEditForm.data.is_active ? 'active' : 'inactive'}
                                                        onChange={(event) =>
                                                            warehouseEditForm.setData(
                                                                'is_active',
                                                                event.target.value === 'active',
                                                            )
                                                        }
                                                    >
                                                        <option value="active">{t('Active')}</option>
                                                        <option value="inactive">{t('Inactive')}</option>
                                                    </ResponsiveSelect>
                                                </label>
                                                <div className="flex gap-2 sm:col-span-2">
                                                    <button
                                                        type="button"
                                                        className="button-secondary"
                                                        disabled={warehouseEditForm.processing}
                                                        onClick={() => saveWarehouse(warehouse)}
                                                    >
                                                        <Save size={14} /> {t('inventory.save_location')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="button-quiet"
                                                        disabled={warehouseEditForm.processing}
                                                        onClick={cancelWarehouseEdit}
                                                    >
                                                        <X size={14} /> {t('Cancel')}
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">{warehouse.name}</p>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {warehouse.code} ·{' '}
                                                        {warehouse.type === 'van'
                                                            ? t('inventory.technician_van')
                                                            : warehouse.type === 'collector'
                                                              ? t('inventory.collector_stock')
                                                              : t('inventory.warehouse')}
                                                    </p>
                                                    {warehouse.assigned_user && (
                                                        <p className="mt-1 text-xs text-muted">
                                                            {t('inventory.custodian')}: {warehouse.assigned_user.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    <span
                                                        className={`text-xs font-semibold ${warehouse.is_active ? 'text-brand' : 'text-muted'}`}
                                                    >
                                                        {warehouse.is_active ? t('Active') : t('Inactive')}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="button-quiet px-2 py-2 text-xs"
                                                        onClick={() => startWarehouseEdit(warehouse)}
                                                    >
                                                        <Edit3 size={14} /> {t('Edit')}
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {catalogWarehouses.length === 0 && (
                                    <p className="px-4 py-8 text-sm text-muted">{t('inventory.no_locations')}</p>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {canTransfer && stockCounts.length > 0 && (
                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line p-5">
                        <p className="section-title">{t('inventory.stock_counts')}</p>
                        <p className="mt-1 text-pretty text-sm text-muted">{t('inventory.stock_counts_description')}</p>
                    </div>
                    <div className="divide-y divide-line">
                        {stockCounts.map((count) => (
                            <div key={count.id} className="p-5">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">{count.counter?.name ?? t('inventory.field_user')}</p>
                                            <span className="text-xs text-muted">{count.warehouse?.code}</span>
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-semibold capitalize ${count.status === 'posted' ? 'bg-emerald-50 text-emerald-700' : count.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'}`}
                                            >
                                                {enumLabel(count.status, t)}
                                            </span>
                                        </div>
                                        <div className="mt-3 overflow-x-auto">
                                            <table className="w-full min-w-[32rem] text-sm">
                                                <thead>
                                                    <tr className="text-xs text-muted">
                                                        <th className="py-2 text-start">{t('Item')}</th>
                                                        <th className="py-2 text-end">{t('inventory.system')}</th>
                                                        <th className="py-2 text-end">{t('inventory.counted')}</th>
                                                        <th className="py-2 text-end">{t('inventory.variance')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-line">
                                                    {count.lines.map((line, index) => (
                                                        <tr key={`${count.id}-${line.item?.sku ?? index}`}>
                                                            <td className="py-2 font-medium">
                                                                {line.item?.name ?? line.item?.sku}
                                                            </td>
                                                            <td className="py-2 text-end tabular-nums">
                                                                {line.expected}
                                                            </td>
                                                            <td className="py-2 text-end tabular-nums">
                                                                {line.counted}
                                                            </td>
                                                            <td
                                                                className={`py-2 text-end font-semibold tabular-nums ${Number(line.variance) < 0 ? 'text-coral' : Number(line.variance) > 0 ? 'text-emerald-700' : 'text-muted'}`}
                                                            >
                                                                {Number(line.variance) > 0 ? '+' : ''}
                                                                {line.variance}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        {count.note && <p className="mt-2 text-xs text-muted">{t('inventory.counter')}: {count.note}</p>}
                                        {count.review_note && (
                                            <p className="mt-1 text-xs text-muted">{t('inventory.review')}: {count.review_note}</p>
                                        )}
                                    </div>
                                    {count.status === 'pending' && (
                                        <div className="flex shrink-0 flex-col gap-2">
                                            <input
                                                className="field lg:w-64"
                                                aria-label={t('inventory.stock_count_review_note')}
                                                value={countReviewForm.data.review_note}
                                                onChange={(event) =>
                                                    countReviewForm.setData('review_note', event.target.value)
                                                }
                                                placeholder={t('inventory.review_note')}
                                            />
                                            <div className="flex gap-2">
                                                <ConfirmDialog
                                                    title={t('inventory.post_variance_title')}
                                                    description={t('inventory.post_variance_description')}
                                                    confirmLabel={t('inventory.post_variance')}
                                                    onConfirm={() => reviewStockCount(count, 'posted')}
                                                >
                                                    <button
                                                        type="button"
                                                        className="button-primary"
                                                        disabled={countReviewForm.processing}
                                                    >
                                                        {t('inventory.post_variance')}
                                                    </button>
                                                </ConfirmDialog>
                                                <ConfirmDialog
                                                    title={t('inventory.reject_count_title')}
                                                    description={t('inventory.reject_count_description')}
                                                    confirmLabel={t('inventory.reject_count')}
                                                    destructive
                                                    onConfirm={() => reviewStockCount(count, 'rejected')}
                                                >
                                                    <button
                                                        type="button"
                                                        className="button-quiet text-coral"
                                                        disabled={countReviewForm.processing}
                                                    >
                                                        {t('Reject')}
                                                    </button>
                                                </ConfirmDialog>
                                            </div>
                                            {countReviewForm.errors.decision && (
                                                <p className="field-error">{countReviewForm.errors.decision}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {canTransfer && transferRequests.length > 0 && (
                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line p-5">
                        <p className="section-title">{t('inventory.stock_requests')}</p>
                        <p className="mt-1 text-pretty text-sm text-muted">
                            {t('inventory.stock_requests_description')}
                        </p>
                    </div>
                    <div className="divide-y divide-line">
                        {transferRequests.map((request) => (
                            <div key={request.id} className="p-5">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">{request.requester?.name ?? t('inventory.field_user')}</p>
                                            <span className="rounded-full bg-sand px-2 py-1 text-xs font-semibold capitalize text-muted">
                                                {request.type}
                                            </span>
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-semibold capitalize ${request.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : request.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'}`}
                                            >
                                                {enumLabel(request.status, t)}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm">
                                            <span className="font-semibold tabular-nums">{request.quantity}</span>{' '}
                                            {request.item?.name ?? request.item?.sku ?? t('inventory.unknown_item')}
                                        </p>
                                        <p className="mt-1 text-pretty text-xs text-muted">
                                            {request.source?.code ?? t('Unknown')} →{' '}
                                            {request.destination?.code ?? t('Unknown')}
                                            {request.note ? ` · ${request.note}` : ''}
                                        </p>
                                        {request.review_note && (
                                            <p className="mt-1 text-xs text-muted">
                                                {t('inventory.review')}: {request.review_note}
                                            </p>
                                        )}
                                    </div>
                                    {request.status === 'pending' && (
                                        <div className="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end">
                                            <label>
                                                <span className="field-label">{t('inventory.review_note')}</span>
                                                <input
                                                    className="field sm:w-64"
                                                    value={reviewForm.data.review_note}
                                                    onChange={(event) =>
                                                        reviewForm.setData('review_note', event.target.value)
                                                    }
                                                    placeholder={t('inventory.optional_handover_note')}
                                                />
                                            </label>
                                            <ConfirmDialog
                                                title={t('inventory.approve_transfer_title')}
                                                description={`${t('This immediately transfers')} ${request.quantity} ${request.item?.name ?? t('items')} ${t('from')} ${request.source?.code ?? t('the source')} ${t('to')} ${request.destination?.code ?? t('the destination')}.`}
                                                confirmLabel={t('inventory.approve_transfer')}
                                                onConfirm={() => reviewTransferRequest(request, 'approved')}
                                            >
                                                <button
                                                    type="button"
                                                    className="button-primary"
                                                    disabled={reviewForm.processing}
                                                >
                                                    {t('inventory.approve')}
                                                </button>
                                            </ConfirmDialog>
                                            <ConfirmDialog
                                                title={t('inventory.reject_request_title')}
                                                description={t('inventory.reject_request_description')}
                                                confirmLabel={t('inventory.reject_request')}
                                                destructive
                                                onConfirm={() => reviewTransferRequest(request, 'rejected')}
                                            >
                                                <button
                                                    type="button"
                                                    className="button-quiet text-coral"
                                                    disabled={reviewForm.processing}
                                                >
                                                    {t('Reject')}
                                                </button>
                                            </ConfirmDialog>
                                        </div>
                                    )}
                                </div>
                                {reviewForm.errors.decision && request.status === 'pending' && (
                                    <p className="field-error mt-2">{reviewForm.errors.decision}</p>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section className="card mt-6 p-5">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="section-title">{t('inventory.bulk_stock')}</p>
                        <p className="mt-1 text-sm text-muted">
                            {t('inventory.bulk_stock_description')}
                        </p>
                    </div>
                    <Package size={18} className="text-brand" />
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {bulkBalances.map((balance) => (
                        <div
                            key={`${balance.inventory_item_id}-${balance.warehouse_id}`}
                            className="rounded-lg border border-line px-4 py-3 text-sm"
                        >
                            <p className="font-semibold">{balance.name ?? balance.sku}</p>
                            <p className="mt-1 text-xs text-muted">
                                {balance.sku} · {balance.warehouse}
                            </p>
                            <p className="mt-2 text-lg font-semibold text-brand">{balance.quantity}</p>
                        </div>
                    ))}
                    {bulkBalances.length === 0 && (
                        <p className="text-sm text-muted">{t('inventory.no_bulk_balances')}</p>
                    )}
                </div>
                {canReceive && bulkItems.length > 0 && bulkWarehouses.length > 0 && (
                    <form
                        onSubmit={submitReceive}
                        className="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                    >
                        <label>
                            <span className="field-label">{t('inventory.material')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={receiveForm.data.inventory_item_id}
                                onChange={(event) => receiveForm.setData('inventory_item_id', event.target.value)}
                            >
                                <option value="">{t('inventory.select_item')}</option>
                                {bulkItems.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.sku} · {item.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">{t('inventory.warehouse')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={receiveForm.data.warehouse_id}
                                onChange={(event) => receiveForm.setData('warehouse_id', event.target.value)}
                            >
                                <option value="">{t('inventory.select_warehouse')}</option>
                                {bulkWarehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>
                                        {warehouse.code} · {warehouse.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">{t('inventory.quantity_received')}</span>
                            <input
                                className="field"
                                inputMode="decimal"
                                value={receiveForm.data.quantity}
                                onChange={(event) => receiveForm.setData('quantity', event.target.value)}
                                placeholder="0.000"
                            />
                            {receiveForm.errors.quantity && (
                                <p className="field-error">{receiveForm.errors.quantity}</p>
                            )}
                        </label>
                        <button type="submit" className="button-secondary" disabled={receiveForm.processing}>
                            {t('inventory.receive_stock')}
                        </button>
                        <label className="sm:col-span-2 lg:col-span-4">
                            <span className="field-label">{t('inventory.note')}</span>
                            <input
                                className="field"
                                value={receiveForm.data.note}
                                onChange={(event) => receiveForm.setData('note', event.target.value)}
                                placeholder={t('inventory.optional_receiving_note')}
                            />
                        </label>
                    </form>
                )}
                {canTransfer && bulkItems.length > 0 && bulkWarehouses.length > 1 && (
                    <form
                        onSubmit={submitBulkTransfer}
                        className="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                    >
                        <div className="sm:col-span-2 lg:col-span-4">
                            <p className="text-sm font-semibold">{t('inventory.move_stock')}</p>
                            <p className="mt-1 text-xs text-muted">
                                {t('inventory.move_stock_description')}
                            </p>
                        </div>
                        <label>
                            <span className="field-label">{t('inventory.material')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={transferForm.data.inventory_item_id}
                                onChange={(event) => transferForm.setData('inventory_item_id', event.target.value)}
                            >
                                <option value="">{t('inventory.select_item')}</option>
                                {bulkItems.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.sku} · {item.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {transferForm.errors.inventory_item_id && (
                                <p className="field-error">{transferForm.errors.inventory_item_id}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('inventory.from')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={transferForm.data.source_warehouse_id}
                                onChange={(event) => transferForm.setData('source_warehouse_id', event.target.value)}
                            >
                                <option value="">{t('inventory.select_source')}</option>
                                {bulkWarehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>
                                        {warehouse.code} · {warehouse.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {transferForm.errors.source_warehouse_id && (
                                <p className="field-error">{transferForm.errors.source_warehouse_id}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('inventory.to')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={transferForm.data.destination_warehouse_id}
                                onChange={(event) =>
                                    transferForm.setData('destination_warehouse_id', event.target.value)
                                }
                            >
                                <option value="">{t('inventory.select_destination')}</option>
                                {bulkWarehouses
                                    .filter(
                                        (warehouse) => String(warehouse.id) !== transferForm.data.source_warehouse_id,
                                    )
                                    .map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>
                                            {warehouse.code} · {warehouse.name}
                                        </option>
                                    ))}
                            </ResponsiveSelect>
                            {transferForm.errors.destination_warehouse_id && (
                                <p className="field-error">{transferForm.errors.destination_warehouse_id}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('inventory.quantity')}</span>
                            <input
                                className="field"
                                inputMode="decimal"
                                value={transferForm.data.quantity}
                                onChange={(event) => transferForm.setData('quantity', event.target.value)}
                                placeholder="0.000"
                            />
                            {transferForm.errors.quantity && (
                                <p className="field-error">{transferForm.errors.quantity}</p>
                            )}
                        </label>
                        <label className="sm:col-span-2 lg:col-span-3">
                            <span className="field-label">{t('inventory.transfer_note')}</span>
                            <input
                                className="field"
                                value={transferForm.data.note}
                                onChange={(event) => transferForm.setData('note', event.target.value)}
                                placeholder={t('inventory.optional_transfer_note')}
                            />
                        </label>
                        <button type="submit" className="button-secondary" disabled={transferForm.processing}>
                            {t('inventory.transfer_stock')}
                        </button>
                    </form>
                )}
            </section>

            <section className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div>
                        <p className="section-title">{t('inventory.movement_audit')}</p>
                        <p className="mt-1 text-sm text-muted">
                            {t('inventory.movement_audit_description')}
                        </p>
                    </div>
                    <label className="min-w-40">
                        <span className="sr-only">{t('inventory.movement_type')}</span>
                        <ResponsiveSelect
                            className="field py-2 text-xs"
                            value={movementType}
                            onChange={(event) => setMovementType(event.target.value)}
                        >
                            <option value="">{t('inventory.all_movement_types')}</option>
                            <option value="receive">{t('inventory.receive')}</option>
                            <option value="consume">{t('inventory.consume')}</option>
                            <option value="assign">{t('inventory.assign')}</option>
                            <option value="return">{t('inventory.return')}</option>
                            <option value="transfer">{t('inventory.transfer')}</option>
                            <option value="transfer_out">{t('inventory.bulk_transfer_out')}</option>
                            <option value="transfer_in">{t('inventory.bulk_transfer_in')}</option>
                        </ResponsiveSelect>
                    </label>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('inventory.when')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.movement')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.item')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.warehouse_column')}</th>
                                <th className="px-5 py-3.5 text-end">{t('inventory.quantity')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.reference')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.actor')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {movements.map((movement) => (
                                <tr key={movement.id}>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(movement.occurred_at)}</td>
                                    <td className="px-5 py-4">
                                        <span className="inline-flex rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold capitalize text-brand">
                                            {inventoryLabel(movement.movement_type)}
                                        </span>
                                        <p className="mt-1 text-xs text-muted">{movement.kind}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">
                                            {movement.item?.name ?? t('inventory.unknown_item')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {movement.serial_number ?? movement.item?.sku ?? '—'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {movement.from_warehouse ? `${movement.from_warehouse} → ` : ''}
                                        {movement.to_warehouse ?? '—'}
                                    </td>
                                    <td className="px-5 py-4 text-end text-sm font-semibold">{movement.quantity}</td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {movement.reference ?? movement.note ?? '—'}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {movement.actor ?? t('inventory.system')}
                                    </td>
                                </tr>
                            ))}
                            {movements.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-muted">
                                        {t('inventory.no_movements')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Package size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {units.total.toLocaleString()} {t('inventory.unit_count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('inventory.audit_note')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('inventory.unit')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.equipment')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.warehouse_column')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('inventory.assigned_service')}</th>
                                <th className="px-5 py-3.5 text-end">{t('inventory.action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {units.data.map((unit) => (
                                <tr key={unit.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{unit.serial_number}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('inventory.unit_number')} #{unit.id}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">
                                            {unit.item?.name ?? t('inventory.unknown_equipment')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">{unit.item?.sku ?? t('inventory.no_sku')}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {unit.warehouse
                                            ? `${unit.warehouse.name} (${unit.warehouse.code})`
                                            : t('inventory.no_warehouse')}
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={unit.status} />
                                        {unit.assigned_at && (
                                            <p className="mt-1 text-xs text-muted">{formatDate(unit.assigned_at)}</p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        {unit.service ? (
                                            unit.service.customer_public_id ? (
                                                <Link
                                                    href={`/customers/${unit.service.customer_public_id}`}
                                                    className="text-sm font-semibold hover:text-brand"
                                                >
                                                    {unit.service.username}
                                                </Link>
                                            ) : (
                                                <span className="text-sm font-semibold">{unit.service.username}</span>
                                            )
                                        ) : (
                                            <span className="text-sm text-muted">{t('Unassigned')}</span>
                                        )}
                                        {unit.service?.customer && (
                                            <p className="mt-1 text-xs text-muted">{unit.service.customer}</p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canAssign && unit.status === 'available' && assignableServices.length > 0 && (
                                            <div className="flex items-center justify-end gap-2">
                                                <ResponsiveSelect
                                                    className="field max-w-56 py-2 text-xs"
                                                    value={selectedServices[unit.id] ?? ''}
                                                    onChange={(event) =>
                                                        setSelectedServices((current) => ({
                                                            ...current,
                                                            [unit.id]: event.target.value,
                                                        }))
                                                    }
                                                >
                                                    <option value="">{t('inventory.select_service')}</option>
                                                    {assignableServices.map((service) => (
                                                        <option key={service.public_id} value={service.public_id}>
                                                            {service.username} · {service.customer ?? t('inventory.no_customer')}
                                                        </option>
                                                    ))}
                                                </ResponsiveSelect>
                                                <ConfirmDialog
                                                    title={t('inventory.assign_unit') + ' ' + unit.serial_number + '?'}
                                                    description={t('inventory.assign_unit_description')}
                                                    confirmLabel={t('inventory.assign_unit')}
                                                    onConfirm={() => assignUnit(unit)}
                                                >
                                                    <button type="button" className="text-sm font-semibold text-brand">
                                                        {t('inventory.assign')}
                                                    </button>
                                                </ConfirmDialog>
                                            </div>
                                        )}
                                        {canTransfer &&
                                            ['available', 'returned'].includes(unit.status) &&
                                            transferWarehouses.length > 0 && (
                                                <div className="mt-2 flex items-center justify-end gap-2">
                                                    <ResponsiveSelect
                                                        className="field max-w-56 py-2 text-xs"
                                                        value={selectedWarehouses[unit.id] ?? ''}
                                                        onChange={(event) =>
                                                            setSelectedWarehouses((current) => ({
                                                                ...current,
                                                                [unit.id]: event.target.value,
                                                            }))
                                                        }
                                                    >
                                                        <option value="">{t('inventory.recover_or_transfer')}</option>
                                                        {transferWarehouses
                                                            .filter(
                                                                (warehouse) => warehouse.code !== unit.warehouse?.code,
                                                            )
                                                            .map((warehouse) => (
                                                                <option key={warehouse.id} value={warehouse.id}>
                                                                    {warehouse.code} · {warehouse.name}
                                                                </option>
                                                            ))}
                                                    </ResponsiveSelect>
                                                    <button
                                                        type="button"
                                                        className="text-sm font-semibold text-brand"
                                                        onClick={() => transferUnit(unit)}
                                                    >
                                                        {t('inventory.transfer')}
                                                    </button>
                                                </div>
                                            )}
                                    </td>
                                </tr>
                            ))}
                            {units.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <Package className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('inventory.no_units')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('inventory.page')} {units.current_page} {t('of')} {units.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {units.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === units.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? (
                                            <ChevronLeft size={16} />
                                        ) : isNext ? (
                                            <ChevronRight size={16} />
                                        ) : (
                                            link.label
                                        )}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? (
                                        <ChevronLeft size={16} />
                                    ) : isNext ? (
                                        <ChevronRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
