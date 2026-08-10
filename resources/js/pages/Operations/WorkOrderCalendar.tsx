import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';

type WorkOrderStatus = 'pending' | 'assigned' | 'en_route' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
type WorkOrder = {
    public_id: string;
    number: string;
    type: string;
    status: WorkOrderStatus;
    scheduled_at: string | null;
    scheduled_at_local: string;
    customer: { public_id: string; name: string } | null;
    assignee: string | null;
};

type Props = {
    weekStart: string;
    timezone: string;
    workOrders: WorkOrder[];
};

const startHour = 8;
const endHour = 20;
const hours = Array.from({ length: endHour - startHour }, (_, index) => startHour + index);

function dateKey(date: Date): string {
    return (
        date.getFullYear() +
        '-' +
        String(date.getMonth() + 1).padStart(2, '0') +
        '-' +
        String(date.getDate()).padStart(2, '0')
    );
}

function addDays(value: string, days: number): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day + days);

    return dateKey(date);
}

function dayLabel(value: string): string {
    const [year, month, day] = value.split('-').map(Number);

    return new Intl.DateTimeFormat('en-US', { weekday: 'short', month: 'short', day: 'numeric' }).format(
        new Date(year, month - 1, day),
    );
}

function slotOrders(orders: WorkOrder[], day: string, hour: number): WorkOrder[] {
    return orders.filter(
        (order) => order.scheduled_at_local.startsWith(day) && Number(order.scheduled_at_local.slice(11, 13)) === hour,
    );
}

export default function WorkOrderCalendarPage({ weekStart, timezone, workOrders }: Props) {
    const days = Array.from({ length: 7 }, (_, index) => addDays(weekStart, index));
    const moveWeek = (daysToMove: number) => {
        router.get(
            '/operations/work-orders/calendar',
            { week: addDays(weekStart, daysToMove) },
            { preserveState: true, replace: true },
        );
    };
    const reschedule = (event: React.DragEvent<HTMLDivElement>, day: string, hour: number) => {
        event.preventDefault();
        const publicId = event.dataTransfer.getData('text/plain');
        if (!publicId) return;
        router.post(
            '/operations/work-orders/' + publicId + '/schedule',
            { scheduled_at: day + 'T' + String(hour).padStart(2, '0') + ':00' },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Work-order calendar" />
            <Link
                href="/operations/work-orders"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to work orders
            </Link>
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Field operations · {timezone}</p>
                    <h1 className="page-title">Work-order calendar</h1>
                    <p className="page-subtitle">Drag an active work order to a new tenant-local time slot.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button type="button" className="button-secondary" onClick={() => moveWeek(-7)}>
                        <ChevronLeft size={16} /> Previous
                    </button>
                    <button type="button" className="button-secondary" onClick={() => moveWeek(7)}>
                        Next <ChevronRight size={16} />
                    </button>
                </div>
            </div>
            <div className="card mt-8 overflow-x-auto">
                <div className="grid min-w-[980px] grid-cols-7 divide-x divide-line">
                    {days.map((day) => (
                        <section key={day} className="min-w-0">
                            <header className="border-b border-line bg-sand/40 px-3 py-3 text-center text-sm font-semibold">
                                {dayLabel(day)}
                            </header>
                            <div>
                                {hours.map((hour) => (
                                    <div
                                        key={hour}
                                        className="min-h-24 border-b border-line p-2"
                                        onDragOver={(event) => event.preventDefault()}
                                        onDrop={(event) => reschedule(event, day, hour)}
                                    >
                                        <p className="text-[11px] font-semibold text-muted">
                                            {String(hour).padStart(2, '0')}:00
                                        </p>
                                        <div className="mt-1 space-y-1">
                                            {slotOrders(workOrders, day, hour).map((order) => (
                                                <div
                                                    key={order.public_id}
                                                    draggable={!['completed', 'cancelled'].includes(order.status)}
                                                    onDragStart={(event) =>
                                                        event.dataTransfer.setData('text/plain', order.public_id)
                                                    }
                                                    className="rounded-lg border border-brand/20 bg-brand-soft px-2 py-2 text-xs"
                                                >
                                                    <Link
                                                        href={'/operations/work-orders/' + order.public_id}
                                                        className="font-semibold hover:text-brand"
                                                    >
                                                        {order.number}
                                                    </Link>
                                                    <p className="mt-1 truncate text-muted">
                                                        {order.customer?.name ?? 'No customer'}
                                                    </p>
                                                    <div className="mt-1 flex items-center justify-between gap-1">
                                                        <StatusBadge status={order.status} />
                                                        <span className="truncate text-muted">
                                                            {order.assignee ?? 'Unassigned'}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
            {workOrders.length === 0 && (
                <div className="mt-6 rounded-xl border border-dashed border-line p-10 text-center text-sm text-muted">
                    <CalendarDays className="mx-auto" size={28} />
                    <p className="mt-3 font-semibold">No scheduled work orders this week.</p>
                </div>
            )}
        </AppLayout>
    );
}
