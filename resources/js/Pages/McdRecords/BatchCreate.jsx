import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import BatchFields from './Partials/BatchFields';
import BatchItemsTable from './Partials/BatchItemsTable';
import RequestPicker from './Partials/RequestPicker';

export default function BatchCreate({ requests, transactions, items }) {
    const { data, setData, post, processing, errors } = useForm({ batchno: '', description: '', item_ids: [] });
    const [selectedRequests, setSelectedRequests] = useState(transactions);
    const [loadingItems, setLoadingItems] = useState(false);
    const firstRender = useRef(true);

    // Choosing requests reloads only the items and clears the checked ones, like legacy.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        setData('item_ids', []);
        router.reload({
            only: ['items', 'transactions'],
            data: { transactions: selectedRequests },
            onStart: () => setLoadingItems(true),
            onFinish: () => setLoadingItems(false),
        });
    }, [selectedRequests]);

    const submit = (e) => {
        e.preventDefault();
        post(route('mcdrecords.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records', href: route('mcdrecords.batches') }, { label: 'Create' }]}
                    title="Create New Batch"
                />
            }
        >
            <Head title="Create New Batch" />

            <form onSubmit={submit} className="space-y-6 p-4 sm:p-6 lg:p-8">
                <Card className="space-y-5 p-6">
                    <BatchFields data={data} setData={setData} errors={errors} />
                    <div className="lg:max-w-xl">
                        <InputLabel>
                            Stock Requests (Completed)<span className="text-red-600"> *</span>
                        </InputLabel>
                        <div className="mt-1">
                            <RequestPicker requests={requests} value={selectedRequests} onChange={setSelectedRequests} disabled={processing} />
                        </div>
                    </div>
                </Card>

                <div className={loadingItems ? 'opacity-60' : ''}>
                    <BatchItemsTable
                        title="Issued Items"
                        items={items}
                        selected={data.item_ids}
                        onSelect={(ids) => setData('item_ids', ids)}
                        empty={selectedRequests.length ? 'No issued items left to batch.' : 'Select stock requests to list their issued items.'}
                    />
                    <InputError message={errors.item_ids} className="mt-2" />
                </div>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={route('mcdrecords.batches')}
                        className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        ← Back to Dashboard
                    </Link>
                    <PrimaryButton disabled={processing || loadingItems}>Save</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
