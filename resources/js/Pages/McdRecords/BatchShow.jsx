import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import BatchFields from './Partials/BatchFields';
import BatchItemsTable from './Partials/BatchItemsTable';

export default function BatchShow({ batch, items }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records', href: route('mcdrecords.batches') }, { label: 'View' }]}
                    title="View Batch"
                    subtitle={`Created ${batch.created_at ?? ''}${batch.created_by ? ` by ${batch.created_by}` : ''}`}
                />
            }
        >
            <Head title={`Batch ${batch.batchno}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <Card className="p-6">
                    <BatchFields data={batch} setData={() => {}} readOnly />
                </Card>

                <BatchItemsTable title="Batch Items" items={items} />

                <Link
                    href={route('mcdrecords.batches')}
                    className="inline-block text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
