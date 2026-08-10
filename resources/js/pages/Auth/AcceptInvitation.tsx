import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, LockKeyhole, UserPlus } from 'lucide-react';

import AuthLayout from '@/layouts/AuthLayout';

type Props = { token: string };

export default function AcceptInvitation({ token }: Props) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(`/invite/${token}`);
    };

    return (
        <AuthLayout>
            <Head title="Accept invitation" />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white"><UserPlus size={22} /></div>
            <p className="eyebrow">Tenant invitation</p>
            <h1 className="page-title">Create your operator account</h1>
            <p className="page-subtitle">Set your name and password to join the ISP Manager workspace.</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label><span className="field-label">Full name</span><input className="field" autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name && <p className="field-error">{form.errors.name}</p>}</label>
                <label><span className="field-label">Password</span><input className="field" type="password" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />{form.errors.password && <p className="field-error">{form.errors.password}</p>}<p className="mt-1 text-xs text-muted">Use at least 12 characters.</p></label>
                <label><span className="field-label">Confirm password</span><input className="field" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></label>
                <button className="button-primary w-full" disabled={form.processing}><CheckCircle2 size={16} /> Accept invitation</button>
                <p className="flex items-center gap-2 text-xs text-muted"><LockKeyhole size={14} /> The invitation is one-time and expires automatically.</p>
            </form>
        </AuthLayout>
    );
}
