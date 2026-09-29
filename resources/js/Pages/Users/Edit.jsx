import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import UserForm from './Partials/UserForm';

export default function Edit({ user, roles, departments }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Maintenance' },
                        { label: 'Users', href: route('users.index') },
                        { label: 'Edit' },
                    ]}
                    title={user.name}
                    subtitle={
                        <span className="inline-flex items-center gap-2">
                            <span className="font-mono">{user.username}</span>
                            <StatusBadge active={user.isActive == 1} />
                        </span>
                    }
                />
            }
        >
            <Head title={`Edit ${user.username}`} />

            <div className="max-w-3xl p-4 sm:p-6 lg:p-8">
                <UserForm user={user} roles={roles} departments={departments} />
            </div>
        </AuthenticatedLayout>
    );
}
