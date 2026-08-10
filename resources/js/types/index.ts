export type User = {
    id: number;
    name: string;
    email: string;
    role: string;
};

export type Tenant = {
    id: string;
    name: string;
    currency: string;
};

export type PageProps = {
    app: {
        name: string;
        locale: string;
        direction: 'ltr' | 'rtl';
    };
    auth: {
        user: User | null;
        tenant: Tenant | null;
    };
    flash: {
        success?: string;
        error?: string;
    };
};

export type DashboardMetrics = {
    customers: number;
    activeServices: number;
    attention: number;
    expiringSoon: number;
    collectionsToday: number;
    collectionsCurrency: string;
    networkPending: number;
    failedCommands: number;
    offlineRouters: number;
    activeSessions: number;
    driftedServices: number;
    openIncidents: number;
    openWorkOrders: number;
};

export type AttentionQueueItem = {
    type: string;
    title: string;
    detail: string;
    href: string;
    severity: 'critical' | 'warning' | 'info';
};

export type FinanceReport = {
    from: string;
    to: string;
    invoice_count: number;
    payment_count: number;
    invoiced_by_currency: Record<string, number>;
    collected_by_currency: Record<string, number>;
    customer_balances_by_currency: Record<string, number>;
};

export type PublicTenant = { slug: string; name: string };

export type Zone = {
    id: number;
    name: string;
    code: string;
};

export type Plan = {
    id: number;
    public_id: string;
    name: string;
    download_kbps: number;
    upload_kbps: number;
    amount_minor: number;
    currency: string;
};

export type Service = {
    id: number;
    public_id: string;
    username: string;
    status: 'pending' | 'active' | 'suspended' | 'terminated';
    network_state: 'unknown' | 'pending_sync' | 'in_sync' | 'drifted' | 'failed';
    expires_at: string | null;
    plan: Plan;
    customer?: Customer;
};

export type ServiceEvent = {
    id: number;
    event_type: string;
    from_status: string | null;
    to_status: string | null;
    created_at: string;
};

export type Customer = {
    id: number;
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
    phone: string;
    email: string | null;
    address: string | null;
    status: 'active' | 'inactive' | 'archived';
    balance_amount: number;
    balance_currency: string;
    zone: Zone | null;
    services: Service[];
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
