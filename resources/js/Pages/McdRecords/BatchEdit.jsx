import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BatchFields from './Partials/BatchFields';
import BatchItemsTable from './Partials/BatchItemsTable';
import RequestPicker from './Partials/RequestPicker';

export default function BatchEdit({ batch, items, requests }) {
    const { data, setData, put, processing, errors } = useForm({ batchno: batch.batchno, description: batch.description ?? '' });
    const [adding, setAdding] = useState([]);
    const [excluding, setExcluding] = useState(null);
    const [busy, setBusy] = useState(false);

    const options = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('mcdrecords.update', batch.id));
    };

    const include = () =>
        router.post(route('mcdrecords.include', batch.id), { transactions: adding }, { ...options, onSuccess: () => setAdding([]) });

    const exclude = () =>
        router.patch(route('mcdrecords.exclude', { batch: batch.id, item: excluding.id }), {}, { ...options, onFinish: () => {
            setBusy(false);
            setExcluding(null);
        } });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records', href: route('mcdrecords.batches') }, { label: 'Edit' }]}
                    title="Edit Batch"
                    subtitle={batch.batchno}
                />
            }
        >
            <Head title={`Edit Batch ${batch.batchno}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <form onSubmit={submit}>
                    <Card className="space-y-5 p-6">
                        <BatchFields data={data} setData={setData} errors={errors} />
                        <div className="flex justify-end lg:max-w-xl">
                            <PrimaryButton disabled={processing || busy}>Save</PrimaryButton>
                        </div>
                    </Card>
                </form>

                <Card className="space-y-3 p-6">
                    <InputLabel>Add Stock Requests (Completed)</InputLabel>
                    <div className="lg:max-w-xl">
                        <RequestPicker requests={requests} value={adding} onChange={setAdding} disabled={busy} />
                    </div>
                    <SecondaryButton onClick={include} disabled={busy || adding.length === 0}>
                        Add their issued items
                    </SecondaryButton>
                </Card>

                <BatchItemsTable title="Batch Items" items={items} onExclude={setExcluding} />

                <Link
                    href={route('mcdrecords.batches')}
                    className="inline-block text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    ← Back to Dashboard
                </Link>
            </div>

            <ConfirmDialog
                show={excluding !== null}
                title="Delete Confirmation"
                message={`Do you want to exclude ${excluding?.stock_code ?? 'this item'} from the batch?`}
                confirmLabel="Exclude"
                danger
                processing={busy}
                onConfirm={exclude}
                onClose={() => !busy && setExcluding(null)}
            />
        </AuthenticatedLayout>
    );
}
