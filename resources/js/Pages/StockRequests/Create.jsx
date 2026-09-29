import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RequestForm from './Partials/RequestForm';

export default function Create({ origins, defaults }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Stock Request' },
                        { label: 'Manage Requests', href: route('stockrequests.index') },
                        { label: 'Create Request' },
                    ]}
                    title="Create Stock Request"
                    subtitle="Save the request first; you can submit it once it is saved."
                />
            }
        >
            <Head title="Create Stock Request" />

            <div className="p-4 sm:p-6 lg:p-8">
                <RequestForm origins={origins} defaults={defaults} />
            </div>
        </AuthenticatedLayout>
    );
}
