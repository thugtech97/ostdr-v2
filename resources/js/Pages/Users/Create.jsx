import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import UserForm from './Partials/UserForm';

export default function Create({ roles, departments }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Maintenance' },
                        { label: 'Users', href: route('users.index') },
                        { label: 'Create' },
                    ]}
                    title="New User"
                />
            }
        >
            <Head title="New User" />

            <div className="max-w-3xl p-4 sm:p-6 lg:p-8">
                <UserForm roles={roles} departments={departments} />
            </div>
        </AuthenticatedLayout>
    );
}
