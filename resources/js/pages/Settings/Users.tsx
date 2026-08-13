import ResponsiveSelect from '@/components/ui/responsive-select';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Copy, Edit3, MailPlus, Save, Search, Users, X } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
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

export default function UsersPage({
    users,
    invitations,
    roles,
    canManageRoles,
    workspaceLocale,
    filters,
    invitation,
}: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [editingUserId, setEditingUserId] = useState<number | null>(null);
    const form = useForm({ email: '', role: roles[0] ?? 'support_agent' });
    const roleForm = useForm({ role: roles[0] ?? 'support_agent' });

    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;

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
            <Head title={t('Users and invitations')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Administration')}</p>
                    <h1 className="page-title">{t('Users and invitations')}</h1>
                    <p className="page-subtitle">{t('users.subtitle')}</p>
                </div>
                <Link href="/settings/general" className="button-secondary">
                    {t('Workspace settings')}
                </Link>
                <Link href="/settings/collector-territories" className="button-secondary">
                    {t('Collector territories')}
                </Link>
            </div>

            {invitation && (
                <div className="card mt-8 border-brand/30 bg-brand-soft p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold">{t('users.invitation_created')}</p>
                            <p className="mt-1 text-xs text-muted">
                                {t('users.send_link_to')} {invitation.email}. {t('users.expires')}{' '}
                                {formatDate(invitation.expires_at)}. {t('users.not_shown_again')}
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
                            <Copy size={15} /> {t('users.copy_link')}
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
                                placeholder={t('users.search_placeholder')}
                                aria-label={t('users.search_placeholder')}
                            />
                        </div>
                        <button type="submit" className="button-secondary">
                            {t('Search')}
                        </button>
                    </form>
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                        <Users size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {users.total.toLocaleString()} {t('users.count')}
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-start">
                            <thead>
                                <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                    <th className="px-5 py-3.5 text-start">{t('users.operator')}</th>
                                    <th className="px-5 py-3.5 text-start">{t('Role')}</th>
                                    <th className="px-5 py-3.5 text-start">{t('Locale')}</th>
                                    <th className="px-5 py-3.5 text-start">{t('Security')}</th>
                                    {canManageRoles && <th className="px-5 py-3.5 text-end">{t('Actions')}</th>}
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
                                                        id={`member-role-${member.id}`}
                                                        className="field py-2 text-xs"
                                                        {...fieldA11y(`member-role-${member.id}`, roleForm.errors.role)}
                                                        value={roleForm.data.role}
                                                        onChange={(event) =>
                                                            roleForm.setData('role', event.target.value)
                                                        }
                                                    >
                                                        {roles.map((role) => (
                                                            <option key={role} value={role}>
                                                                {enumLabel(role, t)}
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                    {fieldError(`member-role-${member.id}`, roleForm.errors.role)}
                                                </div>
                                            ) : (
                                                enumLabel(member.role, t)
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-muted">
                                            {member.locale?.toUpperCase() ?? workspaceLocale.toUpperCase()} ·{' '}
                                            {member.locale
                                                ? t('users.personal_language')
                                                : t('users.workspace_language')}{' '}
                                            · {member.timezone ?? t('users.tenant_time')}
                                        </td>
                                        <td className="px-5 py-4 text-xs text-muted">
                                            <p>
                                                {member.email_verified
                                                    ? t('users.email_verified')
                                                    : t('users.email_unverified')}
                                            </p>
                                            <p className="mt-1">
                                                {member.two_factor_enabled
                                                    ? t('users.2fa_enabled')
                                                    : t('users.2fa_not_configured')}
                                            </p>
                                        </td>
                                        {canManageRoles && (
                                            <td className="px-5 py-4 text-end">
                                                {editingUserId === member.id ? (
                                                    <div className="flex justify-end gap-2">
                                                        <ConfirmDialog
                                                            title={t('users.change_role_title')}
                                                            description={`${t('users.change_role_description')} ${member.name}.`}
                                                            confirmLabel={t('users.save_role')}
                                                            onConfirm={() => saveRole(member)}
                                                        >
                                                            <button
                                                                type="button"
                                                                className="button-secondary px-3 py-2 text-xs"
                                                                disabled={roleForm.processing}
                                                            >
                                                                <Save size={14} /> {t('Save')}
                                                            </button>
                                                        </ConfirmDialog>
                                                        <button
                                                            type="button"
                                                            className="button-quiet px-2 py-2 text-xs"
                                                            onClick={cancelRoleEdit}
                                                            disabled={roleForm.processing}
                                                        >
                                                            <X size={14} /> {t('Cancel')}
                                                        </button>
                                                    </div>
                                                ) : protectedRoles.includes(member.role) ? (
                                                    <span className="text-xs text-muted">{t('Protected')}</span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="button-quiet px-2 py-2 text-xs"
                                                        onClick={() => startRoleEdit(member)}
                                                    >
                                                        <Edit3 size={14} /> {t('users.edit_role')}
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
                                            <p className="mt-3 font-semibold">{t('users.no_matches')}</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t border-line px-5 py-4">
                        <p className="text-xs text-muted">
                            {t('Page')} {users.current_page} {t('of')} {users.last_page}
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
                            <h2 className="section-title">{t('users.invite_operator')}</h2>
                        </div>
                        <p className="text-sm text-muted">{t('users.invite_description')}</p>
                        <label>
                            <span className="field-label">{t('Email')}</span>
                            <input
                                id="invite-email"
                                className="field"
                                type="email"
                                {...fieldA11y('invite-email', form.errors.email)}
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                            {fieldError('invite-email', form.errors.email)}
                        </label>
                        <label>
                            <span className="field-label">{t('Role')}</span>
                            <ResponsiveSelect
                                id="invite-role"
                                className="field"
                                {...fieldA11y('invite-role', form.errors.role)}
                                value={form.data.role}
                                onChange={(event) => form.setData('role', event.target.value)}
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {enumLabel(role, t)}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {fieldError('invite-role', form.errors.role)}
                        </label>
                        <button type="submit" className="button-primary w-full" disabled={form.processing}>
                            <MailPlus size={16} /> {t('users.create_invite')}
                        </button>
                    </form>
                    <div className="card p-6">
                        <h2 className="section-title">{t('users.pending_invites')}</h2>
                        <div className="mt-4 divide-y divide-line">
                            {invitations.map((pending) => (
                                <div
                                    key={`${pending.email}-${pending.created_at}`}
                                    className="py-3 first:pt-0 last:pb-0"
                                >
                                    <p className="text-sm font-semibold">{pending.email}</p>
                                    <p className="mt-1 text-xs capitalize text-muted">
                                        {enumLabel(pending.role, t)} · {t('users.expires')}{' '}
                                        {formatDate(pending.expires_at)}
                                    </p>
                                </div>
                            ))}
                            {invitations.length === 0 && (
                                <p className="text-sm text-muted">{t('users.no_pending_invites')}</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
