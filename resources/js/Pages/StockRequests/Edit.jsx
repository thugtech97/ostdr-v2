import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RequestForm from './Partials/RequestForm';

export default function Edit({ stockRequest, items, origins }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Stock Request' },
                        { label: 'Manage Requests', href: route('stockrequests.index') },
                        { label: 'Edit Request' },
                    ]}
                    title="Edit Stock Transfer Request"
                />
            }
        >
            <Head title={`Edit ${stockRequest.transaction_no}`} />

            <div className="p-4 sm:p-6 lg:p-8">
                <RequestForm key={stockRequest.id} stockRequest={stockRequest} items={items} origins={origins} />
            </div>
        </AuthenticatedLayout>
    );
}
