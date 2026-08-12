import ResponsiveSelect from '@/components/ui/responsive-select';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Copy, Edit3, MailPlus, Save, Search, Users, X } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    locale: string | null;
    timezone: string | null;
    email_verified: boolean;
    two_factor_enabled: boolean;
};

const protectedRoles = ['admin', 'platform_operator', 'tenant_owner'];

type Invitation = { email: string; role: string; expires_at: string | null; created_at: string | null };
type NewInvitation = Invitation & { token: string };

type Props = PageProps & {
    users: Paginator<UserRow>;
    invitations: Invitation[];
    roles: string[];
    canManageRoles: boolean;
    workspaceLocale: 'en' | 'ar' | 'fr';
    filters: { search?: string };
    invitation?: NewInvitation | null;
};

export default function UsersPage({ users, invitations, roles, canManageRoles, workspaceLocale, filters, invitation }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editingUserId, setEditingUserId] = useState<number | null>(null);
    const form = useForm({ email: '', role: roles[0] ?? 'support_agent' });
    const roleForm = useForm({ role: roles[0] ?? 'support_agent' });

    const applySearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/settings/users', { search: search || undefined }, { preserveState: true, replace: true });
    };

    const invite = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/settings/users/invite');
    };

    const inviteLink = invitation ? `${window.location.origin}/invite/${invitation.token}` : '';

    const startRoleEdit = (member: UserRow) => {
        setEditingUserId(member.id);
        roleForm.setData('role', member.role);
        roleForm.clearErrors();
    };

    const cancelRoleEdit = () => {
        setEditingUserId(null);
        roleForm.reset();
        roleForm.clearErrors();
    };

    const saveRole = (member: UserRow) => {
        roleForm.patch(`/settings/users/${member.id}`, {
            onSuccess: () => cancelRoleEdit(),
        });
    };

    return (
        <AppLayout>
            <Head title="Users and invitations" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Administration</p>
                    <h1 className="page-title">Users and invitations</h1>
                    <p className="page-subtitle">
                        Manage tenant operators without exposing passwords or invitation hashes.
                    </p>
                </div>
                <Link href="/settings/general" className="button-secondary">
                    Workspace settings
                </Link>
            </div>

            {invitation && (
                <div className="card mt-8 border-brand/30 bg-brand-soft p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold">One-time invitation link created</p>
                            <p className="mt-1 text-xs text-muted">
                                Send this link to {invitation.email}. It expires {formatDate(invitation.expires_at)} and
                                is not shown again.
                            </p>
                            <code className="mt-3 block break-all rounded-lg bg-white px-3 py-2 text-xs text-ink">
                                {inviteLink}
                            </code>
                        </div>
                        <button
                            type="button"
                            className="button-secondary shrink-0"
                            onClick={() => navigator.clipboard.writeText(inviteLink)}
                        >
                            <Copy size={15} /> Copy link
                        </button>
                    </div>
                </div>
            )}

            <div className="mt-8 grid gap-6 xl:grid-cols-[1fr_0.72fr]">
                <div className="card overflow-hidden">
                    <form onSubmit={applySearch} className="flex gap-3 border-b border-line p-5">
                        <div className="relative flex-1">
                            <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                            <input
                                className="field ps-10"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search name, email, or role"
                            />
                        </div>
                        <button type="submit" className="button-secondary">
                            Search
                        </button>
                    </form>
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                        <Users size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{users.total.toLocaleString()} user(s)</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-start">
                            <thead>
                                <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                    <th className="px-5 py-3.5 text-start">Operator</th>
                                    <th className="px-5 py-3.5 text-start">Role</th>
                                    <th className="px-5 py-3.5 text-start">Locale</th>
                                    <th className="px-5 py-3.5 text-start">Security</th>
                                    {canManageRoles && <th className="px-5 py-3.5 text-end">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {users.data.map((member) => (
                                    <tr key={member.id} className="hover:bg-sand/30">
                                        <td className="px-5 py-4">
                                            <p className="text-sm font-semibold">{member.name}</p>
                                            <p className="mt-1 text-xs text-muted">{member.email}</p>
                                        </td>
                                        <td className="px-5 py-4 text-sm capitalize">
                                            {editingUserId === member.id ? (
                                                <div className="min-w-52 space-y-2">
                                                    <ResponsiveSelect
                                                        className="field py-2 text-xs"
                                                        value={roleForm.data.role}
                                                        onChange={(event) =>
                                                            roleForm.setData('role', event.target.value)
                                                        }
                                                    >
                                                        {roles.map((role) => (
                                                            <option key={role} value={role}>
                                                                {role.replaceAll('_', ' ')}
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                    {roleForm.errors.role && (
                                                        <p className="field-error">{roleForm.errors.role}</p>
                                                    )}
                                                </div>
                                            ) : (
                                                member.role.replaceAll('_', ' ')
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-muted">
                                            {member.locale?.toUpperCase() ?? workspaceLocale.toUpperCase()} ·{' '}
                                            {member.locale ? 'Personal language' : 'Workspace language'} ·{' '}
                                            {member.timezone ?? 'Tenant time'}
                                        </td>
                                        <td className="px-5 py-4 text-xs text-muted">
                                            <p>{member.email_verified ? 'Email verified' : 'Email unverified'}</p>
                                            <p className="mt-1">
                                                {member.two_factor_enabled ? '2FA enabled' : '2FA not configured'}
                                            </p>
                                        </td>
                                        {canManageRoles && (
                                            <td className="px-5 py-4 text-end">
                                                {editingUserId === member.id ? (
                                                    <div className="flex justify-end gap-2">
                                                        <ConfirmDialog
                                                            title="Change operator role?"
                                                            description={`This will update ${member.name}'s workspace permissions.`}
                                                            confirmLabel="Save role"
                                                            onConfirm={() => saveRole(member)}
                                                        >
                                                            <button
                                                                type="button"
                                                                className="button-secondary px-3 py-2 text-xs"
                                                                disabled={roleForm.processing}
                                                            >
                                                                <Save size={14} /> Save
                                                            </button>
                                                        </ConfirmDialog>
                                                        <button
                                                            type="button"
                                                            className="button-quiet px-2 py-2 text-xs"
                                                            onClick={cancelRoleEdit}
                                                            disabled={roleForm.processing}
                                                        >
                                                            <X size={14} /> Cancel
                                                        </button>
                                                    </div>
                                                ) : protectedRoles.includes(member.role) ? (
                                                    <span className="text-xs text-muted">Protected</span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="button-quiet px-2 py-2 text-xs"
                                                        onClick={() => startRoleEdit(member)}
                                                    >
                                                        <Edit3 size={14} /> Edit role
                                                    </button>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={canManageRoles ? 5 : 4} className="px-5 py-14 text-center">
                                            <Users className="mx-auto text-muted" size={28} />
                                            <p className="mt-3 font-semibold">No operators match this search</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t border-line px-5 py-4">
                        <p className="text-xs text-muted">
                            Page {users.current_page} of {users.last_page}
                        </p>
                        <div className="flex items-center gap-1">
                            {users.links.map((link, index) => {
                                const previous = index === 0;
                                const next = index === users.links.length - 1;
                                if (!link.url)
                                    return (
                                        <span key={index} className="grid size-8 place-items-center text-muted/40">
                                            {previous ? (
                                                <ChevronLeft size={16} />
                                            ) : next ? (
                                                <ChevronRight size={16} />
                                            ) : (
                                                link.label
                                            )}
                                        </span>
                                    );
                                return (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                    >
                                        {previous ? (
                                            <ChevronLeft size={16} />
                                        ) : next ? (
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

                <div className="space-y-6">
                    <form onSubmit={invite} className="card space-y-5 p-6">
                        <div className="flex items-center gap-2">
                            <MailPlus size={18} className="text-brand" />
                            <h2 className="section-title">Invite operator</h2>
                        </div>
                        <p className="text-sm text-muted">
                            The invitee sets their own name and password. Owner and platform roles require a separate
                            break-glass process.
                        </p>
                        <label>
                            <span className="field-label">Email</span>
                            <input
                                className="field"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                            {form.errors.email && <p className="field-error">{form.errors.email}</p>}
                        </label>
                        <label>
                            <span className="field-label">Role</span>
                            <ResponsiveSelect
                                className="field"
                                value={form.data.role}
                                onChange={(event) => form.setData('role', event.target.value)}
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {form.errors.role && <p className="field-error">{form.errors.role}</p>}
                        </label>
                        <button className="button-primary w-full" disabled={form.processing}>
                            <MailPlus size={16} /> Create one-time invite
                        </button>
                    </form>
                    <div className="card p-6">
                        <h2 className="section-title">Pending invites</h2>
                        <div className="mt-4 divide-y divide-line">
                            {invitations.map((pending) => (
                                <div
                                    key={`${pending.email}-${pending.created_at}`}
                                    className="py-3 first:pt-0 last:pb-0"
                                >
                                    <p className="text-sm font-semibold">{pending.email}</p>
                                    <p className="mt-1 text-xs capitalize text-muted">
                                        {pending.role.replaceAll('_', ' ')} · expires {formatDate(pending.expires_at)}
                                    </p>
                                </div>
                            ))}
                            {invitations.length === 0 && <p className="text-sm text-muted">No pending invitations.</p>}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
