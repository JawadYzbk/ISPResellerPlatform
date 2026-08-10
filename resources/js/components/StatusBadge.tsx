type Status =
    | 'active'
    | 'inactive'
    | 'archived'
    | 'pending'
    | 'suspended'
    | 'terminated'
    | 'in_sync'
    | 'unknown'
    | 'drifted'
    | 'failed'
    | 'pending_sync'
    | 'running'
    | 'completed'
    | 'awaiting_confirmation'
    | 'abandoned'
    | 'stale'
    | 'online'
    | 'offline'
    | 'draft'
    | 'issued'
    | 'void'
    | 'open'
    | 'in_progress'
    | 'resolved'
    | 'closed'
    | 'cancelled'
    | 'assigned'
    | 'available'
    | 'returned'
    | 'damaged';

const styles: Record<Status, string> = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    inactive: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    archived: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    suspended: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    terminated: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    in_sync: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    unknown: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    drifted: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    pending_sync: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    running: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    awaiting_confirmation: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    abandoned: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    stale: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    online: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    offline: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    draft: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    issued: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    void: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    open: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    in_progress: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    closed: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    cancelled: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    assigned: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    available: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    returned: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    damaged: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

export function StatusBadge({ status }: { status: Status }) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ring-inset ${styles[status]}`}
        >
            {status.replace('_', ' ')}
        </span>
    );
}

export default StatusBadge;
