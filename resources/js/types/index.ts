export type Locale = 'en' | 'ar' | 'fr';

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
    logo_url: string | null;
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
        isPlatformOperator: boolean;
        permissions: string[];
    };
    flash: {
        id?: string | null;
        successTitle?: string;
        success?: string;
        error?: string;
        importResult?: ImportBatchResult;
        publicLink?: { url: string; expires_at: string };
    };
};

export type ImportBatchReportRow = {
    row: number;
    status: string;
    errors: string[];
    [key: string]: unknown;
};

export type ImportBatchResult = {
    id: string;
    type: string;
    filename: string;
    status: string;
    total_rows: number;
    successful_rows: number;
    failed_rows: number;
    report: ImportBatchReportRow[];
};

export type DashboardMetrics = {
    customers: number;
    activeServices: number;
    attention: number;
    expiringSoon: number;
    collectionsTodayByCurrency: Record<string, number>;
    networkPending: number;
    failedCommands: number;
    offlineRouters: number;
    activeSessions: number;
    driftedServices: number;
    openIncidents: number;
    openWorkOrders: number;
    owner: {
        period: { from: string; to: string };
        baseCurrency: string;
        revenue: number;
        collected: number;
        collectionRate: number | null;
        margin: number;
        currencyMetrics: Record<
            string,
            { revenue: number; collected: number; collectionRate: number | null; margin: number }
        >;
        statusTrend: { month: string; active: number; suspended: number }[];
    } | null;
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
    collection_rate_by_currency: Record<string, number | null>;
    cash_reconciliation: {
        closed_shift_count: number;
        variance_shift_count: number;
        variance_by_currency: Record<string, number>;
    };
    collection_trend: {
        date: string;
        invoiced_by_currency: Record<string, number>;
        collected_by_currency: Record<string, number>;
    }[];
    aging_by_currency: Record<string, Record<'current' | '1_30' | '31_60' | '61_90' | '90_plus', number>>;
    outstanding_by_currency: Record<string, number>;
    customer_balances_by_currency: Record<string, number>;
    revenue_by_plan: Record<string, Record<string, number>>;
    revenue_by_zone: Record<string, Record<string, number>>;
    margin_by_pop: Record<
        string,
        {
            revenue_by_currency: Record<string, number>;
            upstream_cost_by_currency: Record<string, number>;
            margin_by_currency: Record<string, number>;
        }
    >;
    tax_by_currency: Record<string, number>;
    churned_services: number;
    retention_by_period: {
        active_at_period_start: number;
        terminated_services: number;
        retention_rate_percent: number | null;
    };
    active_customer_count: number;
    arpu_by_currency: Record<string, number | null>;
    top_usage: { service_id: string | null; username: string | null; total_octets: number }[];
    collector_performance: {
        collector: string;
        payment_count: number;
        totals_by_currency: Record<string, number>;
    }[];
    supplier_payables: {
        bill_count: number;
        payment_count: number;
        billed_by_currency: Record<string, number>;
        paid_by_currency: Record<string, number>;
        outstanding_by_currency: Record<string, number>;
        aging_by_currency: Record<string, Record<'current' | '1_30' | '31_60' | '61_90' | '90_plus', number>>;
    };
};

export type SupplierPayablesReport = {
    as_of: string;
    supplier_id: number | null;
    include_settled: boolean;
    summary: {
        bill_count: number;
        open_bill_count: number;
        billed_by_currency: Record<string, number>;
        paid_by_currency: Record<string, number>;
        outstanding_by_currency: Record<string, number>;
        aging_by_currency: Record<string, Record<'current' | '1_30' | '31_60' | '61_90' | '90_plus', number>>;
    };
    by_supplier: {
        supplier_id: number;
        supplier_name: string;
        supplier_code: string | null;
        bill_count: number;
        outstanding_by_currency: Record<string, number>;
        aging_by_currency: Record<string, Record<'current' | '1_30' | '31_60' | '61_90' | '90_plus', number>>;
    }[];
    bills: {
        id: number;
        supplier_id: number;
        supplier_name: string;
        supplier_code: string | null;
        reference: string;
        period_start: string;
        period_end: string;
        currency: string;
        amount: number;
        paid_amount: number;
        outstanding_amount: number;
        age_days: number;
        bucket: 'current' | '1_30' | '31_60' | '61_90' | '90_plus';
        status: 'open' | 'partially_paid' | 'paid';
        last_paid_at: string | null;
    }[];
};

export type OperationsReport = {
    generated_at: string;
    report_from: string;
    report_to: string;
    service_counts_by_status: Record<string, number>;
    expiring_services: number;
    work_order_counts_by_status: Record<string, number>;
    incident_counts_by_status: Record<string, number>;
    active_sessions: number;
    offline_routers: number;
    network_drift: number;
    failed_commands: number;
    low_stock_items: { sku: string; name: string; available_units: number | string; reorder_level: number }[];
    supplier_credentials: {
        from: string;
        to: string;
        expiring_days: number;
        totals: { purchased: number; assigned: number; available: number; expiring: number; revoked_invalid: number };
        by_supplier: {
            name: string;
            code: string | null;
            purchased: number;
            assigned: number;
            available: number;
            expiring: number;
            revoked_invalid: number;
            cost_by_currency: Record<string, number>;
            contracts: {
                id: number | null;
                reference: string | null;
                service_type: string | null;
                purchased: number;
                cost_by_currency: Record<string, number>;
            }[];
        }[];
    };
};

export type PublicTenant = { slug: string; name: string; logo_url: string | null; locale: Locale };

export type PortalBilling = {
    invoices: {
        id: string;
        number: string;
        status: string;
        currency: string;
        total_amount: number;
        outstanding_amount: number;
        due_at: string | null;
        issued_at: string | null;
        lines: { description: string; amount: number; currency: string }[];
    }[];
    payments: {
        id: string;
        number: string;
        status: string;
        currency: string;
        amount: number;
        received_at: string | null;
    }[];
    online_payments: { enabled: boolean; provider: string };
};

export type PortalBalance = {
    balance: { amount: number; currency: string };
    next_due: { invoice_id: string; number: string; amount: number; currency: string; due_at: string | null } | null;
};

export type PortalNotice = {
    uuid: string;
    type: string;
    severity: 'critical' | 'warning' | 'info' | string;
    title: string;
    description: string | null;
    opened_at: string | null;
};

export type PortalTicket = {
    uuid: string;
    number: string;
    subject: string;
    category: string;
    priority: string;
    status: string;
    satisfaction_rating: number | null;
    due_at: string | null;
    updated_at: string | null;
    message_count: number;
};

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
    id?: number;
    public_id: string;
    username: string;
    status: 'pending' | 'active' | 'suspended' | 'paused' | 'terminated';
    suspension_reason?: string | null;
    paused_until?: string | null;
    network_state: 'unknown' | 'pending_sync' | 'in_sync' | 'drifted' | 'failed';
    provisioning_mode?: 'manual' | 'radius' | 'mikrotik' | 'external' | 'upstream_credential';
    expires_at: string | null;
    router?: { public_id: string; name: string } | null;
    usage: { used_bytes: number; quota_bytes: number };
    equipment: {
        id: number;
        serial_number: string;
        status: string;
        assigned_at: string | null;
        item: { sku: string; name: string; category: string } | null;
    }[];
    session?: {
        acct_session_id: string;
        nasname: string | null;
        framed_ip: string | null;
        started_at: string | null;
        last_seen_at: string | null;
        input_octets: number;
        output_octets: number;
    } | null;
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
    id?: number;
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
    phone: string;
    phone_normalized?: string;
    email: string | null;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    documents: {
        id: string;
        filename: string;
        mime_type: string;
        size_bytes: number;
        document_type: string | null;
        retention_until: string | null;
        created_at: string | null;
        download_url: string;
    }[];
    status: 'active' | 'inactive' | 'archived';
    anonymized_at?: string | null;
    balance_amount: number;
    balance_currency: string;
    notification_preferences?: Record<string, unknown> | null;
    zone: Zone | null;
    services: Service[];
    invoices: {
        public_id: string;
        number: string;
        status: string;
        currency: string;
        total_amount: number;
        due_at: string | null;
        issued_at: string | null;
    }[];
    payments: {
        public_id: string;
        number: string;
        status: string;
        currency: string;
        amount: number;
        method: string;
        received_at: string | null;
    }[];
    tickets: {
        public_id: string;
        number: string;
        subject: string;
        priority: string;
        status: 'open' | 'in_progress' | 'pending' | 'resolved' | 'closed';
        due_at: string | null;
        updated_at: string | null;
    }[];
    timeline: {
        type: string;
        title: string;
        detail: string;
        created_at: string | null;
        amount?: number;
        currency?: string;
    }[];
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
