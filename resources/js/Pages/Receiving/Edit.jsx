import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { AlertTriangleIcon, HistoryIcon, XIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import RequestDetails from '@/Components/RequestDetails';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function HistoryModal({ item, onClose }) {
    const history = item?.history ?? [];
    const totalIssued = history.reduce((sum, row) => sum + Number(row.issuance_qty || 0), 0);
    // Entries are oldest-first, so the current balance is the last one's.
    const balance = history.length ? Number(history[history.length - 1].balance || 0) : 0;

    return (
        <Modal show={item !== null} maxWidth="lg" onClose={onClose}>
            <div className="p-6 dark:bg-gray-900">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Issuance History</h2>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {item?.stock_code} · {item?.description}
                </p>

                {history.length === 0 ? (
                    <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Nothing has been issued for this item yet.</p>
                ) : (
                    <>
                        <dl className="mt-4 grid grid-cols-3 gap-3 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Issuances</dt>
                                <dd className="font-semibold text-gray-900 dark:text-gray-100">{history.length}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Total Issued</dt>
                                <dd className="font-semibold text-gray-900 dark:text-gray-100">{totalIssued}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Balance</dt>
                                <dd className={'font-semibold ' + (balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-700 dark:text-green-400')}>
                                    {balance > 0 ? `${balance} remaining` : 'Fully issued'}
                                </dd>
                            </div>
                        </dl>

                        <ol className="mt-4 max-h-80 space-y-3 overflow-y-auto">
                            {history.map((row) => (
                                <li key={row.id} className="rounded-md border border-gray-200 p-3 text-sm dark:border-gray-700">
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium text-gray-900 dark:text-gray-100">{row.item_code}</span>
                                        <span className="font-semibold text-green-700 dark:text-green-400">+{row.issuance_qty}</span>
                                    </div>
                                    <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                                        <div>Date Received: {row.date}</div>
                                        <div>Time: {row.time}</div>
                                        <div className="col-span-2 truncate">Received By: {row.received_by || '—'}</div>
                                        <div>Issued By: {row.issued_by || '—'}</div>
                                        <div>Remaining balance: {row.balance}</div>
                                    </dl>
                                </li>
                            ))}
                        </ol>
                    </>
                )}

                <div className="mt-6 flex justify-end">
                    <SecondaryButton onClick={onClose}>Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    );
}

function CloseItemModal({ item, onClose }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setRemarks('');
        setError(null);
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();

        if (!remarks.trim()) {
            setError('Whoops! Closing cannot proceed without specifying a reason.');
            return;
        }

        router.patch(route('mcds.items.close', item.id), { remarks }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: (errors) => setError(errors.remarks ?? Object.values(errors)[0]),
        });
    };

    return (
        <Modal show={item !== null} maxWidth="md" onClose={() => !processing && close()}>
            <form onSubmit={submit} className="p-6 dark:bg-gray-900">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Close Item (Item id: {item?.id})</h2>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Description: <b>{item?.description}</b>
                </p>
                <TextInput
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                    placeholder="Reason for closing..."
                    className="mt-4 block w-full"
                    maxLength={255}
                    isFocused
                />
                <InputError message={error} className="mt-2" />
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={close} disabled={processing}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>Submit</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

export default function Edit({ stockRequest, items }) {
    const { errors } = usePage().props;
    const [quantities, setQuantities] = useState({});
    const [inputErrors, setInputErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [historyOf, setHistoryOf] = useState(null);
    const [closing, setClosing] = useState(null);
    const [excluding, setExcluding] = useState(null);

    // Validate as the receiver types, the way legacy does: digits only, above 0, within balance.
    const updateQuantity = (item, value) => {
        let error = null;
        let next = value.trim();

        if (next !== '') {
            if (!/^\d+$/.test(next)) {
                error = 'Input numeric characters only!';
                next = '';
            } else if (parseInt(next, 10) > item.balance) {
                error = `Overissue: Maximum issuance quantity is ${item.balance} ${item.uom}/s only!`;
                next = '';
            } else if (parseInt(next, 10) <= 0) {
                error = 'Issuance Quantity cannot be 0/negative!';
                next = '';
            }
        }

        setQuantities((current) => ({ ...current, [item.id]: next }));
        setInputErrors((current) => ({ ...current, [item.id]: error }));
    };

    const save = () =>
        router.post(
            route('mcds.issue', stockRequest.id),
            { items: items.map((item) => ({ id: item.id, issuance_qty: quantities[item.id] || null })) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const exclude = () =>
        router.delete(route('mcds.items.exclude', excluding.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setExcluding(null);
            },
        });

    const errorList = Object.values(errors ?? {});

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Receiving', href: route('mcds.index') }, { label: 'Update Request' }]}
                    title="Update Stock Transfer Request"
                    subtitle={`Transaction # ${stockRequest.transaction_no}`}
                />
            }
        >
            <Head title={`Update ${stockRequest.transaction_no}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {errorList.length > 0 && (
                    <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        <p className="font-medium">Whoops! Something didn't work.</p>
                        <ul className="mt-1 list-inside list-disc">
                            {errorList.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <RequestDetails stockRequest={stockRequest} />

                <Card>
                    <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Items to Issue</h2>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Fully issued and closed items are not listed.</p>
                    </div>
                    <DataTable
                        headers={[
                            { label: '#' },
                            { label: 'Stock Code' },
                            { label: 'Available Qty.' },
                            { label: 'Description' },
                            { label: 'UoM' },
                            { label: 'Requested Qty.' },
                            { label: 'Issued Qty.' },
                            { label: 'Balance' },
                            { label: 'Issuance Qty.' },
                            { label: 'Actions', align: 'right' },
                        ]}
                        count={items.length}
                        empty="No record found."
                    >
                        {items.map((item, index) => (
                            <DataTable.Row key={item.id}>
                                <DataTable.Cell className="text-gray-500">{index + 1}</DataTable.Cell>
                                <DataTable.Cell strong className="font-mono text-xs">{item.stock_code}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.available_qty}</DataTable.Cell>
                                <DataTable.Cell wrap>{item.description}</DataTable.Cell>
                                <DataTable.Cell>{item.uom}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.requested_qty}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.issued_qty}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.balance}</DataTable.Cell>
                                <DataTable.Cell>
                                    <TextInput
                                        inputMode="numeric"
                                        value={quantities[item.id] ?? ''}
                                        onChange={(e) => updateQuantity(item, e.target.value)}
                                        className="w-24 border-red-300 py-1 text-sm"
                                        aria-label={`Issuance quantity for ${item.stock_code}`}
                                        disabled={processing}
                                    />
                                    <InputError message={inputErrors[item.id]} className="mt-1 max-w-[12rem] whitespace-normal text-xs" />
                                </DataTable.Cell>
                                <DataTable.Actions>
                                    <IconButton label="Show History" onClick={() => setHistoryOf(item)} disabled={processing}>
                                        <HistoryIcon />
                                    </IconButton>
                                    <IconButton label="Force Close" tone="danger" onClick={() => setClosing(item)} disabled={processing}>
                                        <AlertTriangleIcon />
                                    </IconButton>
                                    <IconButton label="Exclude Item" tone="danger" onClick={() => setExcluding(item)} disabled={processing}>
                                        <XIcon />
                                    </IconButton>
                                </DataTable.Actions>
                            </DataTable.Row>
                        ))}
                    </DataTable>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={route('mcds.index')}
                        className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        ← Back to Dashboard
                    </Link>
                    <PrimaryButton onClick={save} disabled={processing || items.length === 0}>
                        Save
                    </PrimaryButton>
                </div>
            </div>

            <HistoryModal item={historyOf} onClose={() => setHistoryOf(null)} />
            <CloseItemModal item={closing} onClose={() => setClosing(null)} />
            <ConfirmDialog
                show={excluding !== null}
                title="Delete Confirmation"
                message={`Do you want to exclude ${excluding?.stock_code || 'this item'}?`}
                confirmLabel="Exclude"
                danger
                processing={processing}
                onConfirm={exclude}
                onClose={() => !processing && setExcluding(null)}
            />
        </AuthenticatedLayout>
    );
}
