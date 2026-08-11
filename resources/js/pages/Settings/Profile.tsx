import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Save, ShieldCheck } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Profile = {
    name: string;
    email: string;
    role: string;
    locale: 'en' | 'ar' | 'fr';
    timezone: string | null;
};

type Props = { profile: Profile };

export default function ProfilePage({ profile }: Props) {
    const form = useForm<Profile>(profile);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch('/profile');
    };

    return (
        <AppLayout>
            <Head title="Profile" />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to dashboard
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">Account</p>
                <h1 className="page-title">Your profile</h1>
                <p className="page-subtitle">Keep your name, language, and working timezone up to date.</p>

                <form onSubmit={submit} className="card mt-8 space-y-7 p-6">
                    <section>
                        <div className="flex items-center gap-2">
                            <ShieldCheck size={18} className="text-brand" />
                            <h2 className="section-title">Account details</h2>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">Name</span>
                                <input
                                    className="field"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    autoComplete="name"
                                />
                                {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                            </label>
                            <label>
                                <span className="field-label">Email</span>
                                <input className="field bg-sand" value={form.data.email} disabled />
                                <p className="mt-1 text-xs text-muted">
                                    Email changes are handled by an administrator.
                                </p>
                            </label>
                            <label>
                                <span className="field-label">Role</span>
                                <input
                                    className="field bg-sand capitalize"
                                    value={form.data.role.replaceAll('_', ' ')}
                                    disabled
                                />
                            </label>
                            <label>
                                <span className="field-label">Language</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={form.data.locale}
                                    onChange={(event) =>
                                        form.setData('locale', event.target.value as Profile['locale'])
                                    }
                                >
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                    <option value="fr">French</option>
                                </ResponsiveSelect>
                            </label>
                            <label className="sm:col-span-2">
                                <span className="field-label">Personal timezone</span>
                                <input
                                    className="field"
                                    value={form.data.timezone ?? ''}
                                    onChange={(event) => form.setData('timezone', event.target.value || null)}
                                    placeholder="Leave blank to use the workspace timezone"
                                    autoComplete="off"
                                />
                                {form.errors.timezone && <p className="field-error">{form.errors.timezone}</p>}
                            </label>
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                        <div className="flex flex-wrap gap-2">
                            <Link href="/security/sessions" className="button-secondary">
                                <KeyRound size={16} /> Active sessions
                            </Link>
                            <Link href="/settings/general" className="button-secondary">
                                Workspace settings
                            </Link>
                        </div>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> Save profile
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
