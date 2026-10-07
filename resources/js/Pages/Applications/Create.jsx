import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ScheduleForm from './Partials/ScheduleForm';

export default function Create() {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Application Maintenance', href: route('applications.index') }, { label: 'Create' }]}
                    title="Schedule a Shutdown"
                />
            }
        >
            <Head title="Schedule a Shutdown" />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <ScheduleForm />
            </div>
        </AuthenticatedLayout>
    );
}
