import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import SatelliteForm from './Partials/SatelliteForm';

export default function Create() {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Satellites', href: route('satellites.index') }, { label: 'Create' }]}
                    title="Create New Satellite"
                />
            }
        >
            <Head title="New Satellite" />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <SatelliteForm />
            </div>
        </AuthenticatedLayout>
    );
}
