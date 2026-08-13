export type Status =
    | 'active'
    | 'inactive'
    | 'archived'
    | 'pending'
    | 'suspended'
    | 'paused'
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
    | 'planned'
    | 'issued'
    | 'void'
    | 'posted'
    | 'reversed'
    | 'rejected'
    | 'imported'
    | 'reserved'
    | 'invalid'
    | 'open'
    | 'in_progress'
    | 'resolved'
    | 'closed'
    | 'cancelled'
    | 'assigned'
    | 'acknowledged'
    | 'en_route'
    | 'available'
    | 'returned'
    | 'damaged'
    | 'reserved'
    | 'expired'
    | 'revoked'
    | 'free'
    | 'conflict'
    | 'maintenance'
    | 'full'
    | 'retired'
    | 'down'
    | 'decommissioned';

const styles: Record<Status, string> = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    inactive: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    archived: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    suspended: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    paused: 'bg-violet-50 text-violet-700 ring-violet-600/20',
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
    planned: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    issued: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    void: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    posted: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    reversed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    rejected: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    imported: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    reserved: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    expired: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    revoked: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    invalid: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    open: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    in_progress: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    closed: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    cancelled: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    assigned: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    acknowledged: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    en_route: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    available: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    returned: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    damaged: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    free: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    conflict: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    maintenance: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    full: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    retired: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    down: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    decommissioned: 'bg-slate-100 text-slate-600 ring-slate-500/20',
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
