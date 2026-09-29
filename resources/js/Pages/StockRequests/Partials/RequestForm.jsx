import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import IconButton from '@/Components/IconButton';
import { PlusIcon, PrinterIcon, SendIcon, TrashIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ProductAutocomplete from './ProductAutocomplete';

const MAX_ITEMS = 10;
const EMPTY_LINE = { stock_code: '', description: '', uom: '', requested_qty: '', remarks: '' };

function ReadOnlyField({ id, label, value }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput id={id} value={value ?? ''} disabled className="mt-1 block w-full bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400" />
        </div>
    );
}

/**
 * Create/edit form for a stock transfer request. `stockRequest` is null when creating.
 */
export default function RequestForm({ stockRequest = null, items = [], origins, defaults = {} }) {
    const isEdit = stockRequest !== null;
    const header = isEdit ? stockRequest : defaults;

    const { data, setData, post, put, transform, processing, errors } = useForm({
        origin: stockRequest?.origin ?? (origins.includes('MCD MINE') ? 'MCD MINE' : (origins[0] ?? '')),
        date_needed: stockRequest?.date_needed ?? '',
        requestor: stockRequest?.requestor ?? defaults.requestor ?? '',
        remarks: stockRequest?.remarks ?? '',
        items: items.map(({ stock_code, description, uom, requested_qty, remarks }) => ({ stock_code, description, uom, requested_qty, remarks: remarks ?? '' })),
        submit: false,
    });

    const [line, setLine] = useState(EMPTY_LINE);
    const [lineError, setLineError] = useState(null);
    const [confirmSubmit, setConfirmSubmit] = useState(false);

    const serverErrors = Object.values(errors);

    const selectProduct = (product) =>
        setLine((current) => ({ ...current, stock_code: product.code, description: product.name ?? '', uom: product.uom ?? '' }));

    // Same checks, order and wording as the legacy form.
    const addItem = () => {
        const problem =
            (!line.uom && 'Required Field: Item not found!') ||
            (!line.stock_code && 'Required Field: Stock Code is required!') ||
            (!line.description && 'Required Field: Description is required!') ||
            ((!line.requested_qty || Number(line.requested_qty) <= 0) && 'Required Field: Requested Qty. is required!') ||
            (!line.remarks.trim() && 'Required Field: Item Remarks is required!') ||
            (data.items.some((item) => item.stock_code === line.stock_code || item.description === line.description) &&
                'Stock Code or Description already exists in the item list!') ||
            (data.items.length >= MAX_ITEMS && `Reached the maximum limit of ${MAX_ITEMS} items per transaction!`);

        if (problem) {
            setLineError(problem);
            return;
        }

        setData('items', [...data.items, { ...line, requested_qty: parseInt(line.requested_qty, 10), remarks: line.remarks.trim() }]);
        setLine(EMPTY_LINE);
        setLineError(null);
    };

    const updateQty = (index, value) =>
        setData(
            'items',
            data.items.map((item, i) => (i === index ? { ...item, requested_qty: value === '' ? '' : parseInt(value, 10) } : item)),
        );

    const removeItem = (index) => setData('items', data.items.filter((_, i) => i !== index));

    const save = (e) => {
        e.preventDefault();
        transform((payload) => ({ ...payload, submit: false }));
        isEdit ? put(route('stockrequests.update', stockRequest.id), { preserveScroll: true }) : post(route('stockrequests.store'));
    };

    const submitRequest = () => {
        transform((payload) => ({ ...payload, submit: true }));
        put(route('stockrequests.update', stockRequest.id), {
            preserveScroll: true,
            onFinish: () => setConfirmSubmit(false),
        });
    };

    return (
        <form onSubmit={save} className="space-y-6">
            {serverErrors.length > 0 && (
                <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    <p className="font-medium">Whoops! Something didn't work.</p>
                    <ul className="mt-2 list-disc pl-5">
                        {[...new Set(serverErrors)].map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Card className="p-6">
                {isEdit && (
                    <div className="mb-5 flex justify-end">
                        <div className="w-full sm:w-64">
                            <ReadOnlyField id="transaction_no" label="Transaction #" value={stockRequest.transaction_no} />
                        </div>
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    <div className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <ReadOnlyField id="date_filed" label="Date Filed" value={header.date_filed} />
                            <ReadOnlyField id="time_filed" label="Time Filed" value={header.time_filed} />
                        </div>
                        <div>
                            <InputLabel htmlFor="origin" value="Stock Transfer From *" />
                            <SelectInput id="origin" value={data.origin} onChange={(e) => setData('origin', e.target.value)} className="mt-1 block w-full" required>
                                {origins.map((origin) => (
                                    <option key={origin} value={origin}>
                                        {origin}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError message={errors.origin} className="mt-2" />
                        </div>
                        <ReadOnlyField id="requested_by" label="Created By" value={header.requested_by} />
                    </div>

                    <div className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="date_needed" value="Date Needed *" />
                                <TextInput
                                    id="date_needed"
                                    type="date"
                                    value={data.date_needed ?? ''}
                                    onChange={(e) => setData('date_needed', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                                <InputError message={errors.date_needed} className="mt-2" />
                            </div>
                            <div>
                                <InputLabel htmlFor="requestor" value="Requestor Name *" />
                                <TextInput
                                    id="requestor"
                                    value={data.requestor}
                                    onChange={(e) => setData('requestor', e.target.value)}
                                    className="mt-1 block w-full"
                                    maxLength={255}
                                    required
                                />
                                <InputError message={errors.requestor} className="mt-2" />
                            </div>
                        </div>
                        <ReadOnlyField id="dept" label="Stock Transfer To" value={header.dept} />
                        <div>
                            <InputLabel htmlFor="remarks" value="Remarks" />
                            <textarea
                                id="remarks"
                                rows={2}
                                value={data.remarks ?? ''}
                                onChange={(e) => setData('remarks', e.target.value)}
                                maxLength={255}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            />
                            <InputError message={errors.remarks} className="mt-2" />
                        </div>
                    </div>
                </div>
            </Card>

            <Card>
                <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Requested Items</h2>
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {data.items.length} / {MAX_ITEMS} items
                    </span>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                {['#', 'Stock Code', 'Description', 'UoM', 'Requested Qty.', 'Remarks', ''].map((heading, i) => (
                                    <th key={i} scope="col" className="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {data.items.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No record found.
                                    </td>
                                </tr>
                            )}
                            {data.items.map((item, index) => (
                                <tr key={item.stock_code}>
                                    <td className="px-4 py-2 text-gray-500 dark:text-gray-400">{index + 1}</td>
                                    <td className="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">{item.stock_code}</td>
                                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.description}</td>
                                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.uom}</td>
                                    <td className="px-4 py-2">
                                        <TextInput
                                            type="number"
                                            min={1}
                                            value={item.requested_qty}
                                            onChange={(e) => updateQty(index, e.target.value)}
                                            className="w-24 py-1 text-sm"
                                            aria-label={`Requested quantity for ${item.stock_code}`}
                                        />
                                        <InputError message={errors[`items.${index}.requested_qty`]} className="mt-1" />
                                    </td>
                                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.remarks}</td>
                                    <td className="px-4 py-2 text-right">
                                        <IconButton label="Remove item" tone="danger" onClick={() => removeItem(index)}>
                                            <TrashIcon />
                                        </IconButton>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-gray-200 p-4 dark:border-gray-800">
                    <div className="grid gap-3 lg:grid-cols-12 lg:items-start">
                        <ProductAutocomplete
                            id="line_stock_code"
                            field="code"
                            value={line.stock_code}
                            onChange={(value) => setLine({ ...line, stock_code: value, uom: '' })}
                            onSelect={selectProduct}
                            placeholder="Search Stock Code"
                            className="lg:col-span-2"
                        />
                        <TextInput value={line.uom} disabled placeholder="UoM" className="bg-gray-50 lg:col-span-1 dark:bg-gray-800" aria-label="Unit of measure" />
                        <ProductAutocomplete
                            id="line_description"
                            field="name"
                            value={line.description}
                            onChange={(value) => setLine({ ...line, description: value, uom: '' })}
                            onSelect={selectProduct}
                            placeholder="Search Item Description"
                            className="lg:col-span-4"
                        />
                        <TextInput
                            type="number"
                            min={1}
                            value={line.requested_qty}
                            onChange={(e) => setLine({ ...line, requested_qty: e.target.value })}
                            placeholder="Requested Qty."
                            className="lg:col-span-2"
                            aria-label="Requested quantity"
                        />
                        <TextInput
                            value={line.remarks}
                            onChange={(e) => setLine({ ...line, remarks: e.target.value })}
                            onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addItem())}
                            placeholder="Remarks"
                            maxLength={255}
                            className="lg:col-span-2"
                            aria-label="Item remarks"
                        />
                        <SecondaryButton onClick={addItem} className="justify-center gap-1.5 lg:col-span-1">
                            <PlusIcon />
                            Add
                        </SecondaryButton>
                    </div>
                    {lineError && (
                        <p role="alert" className="mt-2 text-sm text-red-600 dark:text-red-400">
                            {lineError}
                        </p>
                    )}
                    <InputError message={errors.items} className="mt-2" />
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    href={route('stockrequests.index')}
                    className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    ← Back to Dashboard
                </Link>
                <div className="flex flex-wrap items-center gap-3">
                    {isEdit && (
                        <a
                            href={route('stockrequests.print', stockRequest.id)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <PrinterIcon />
                            Print
                        </a>
                    )}
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    {isEdit && (
                        <button
                            type="button"
                            onClick={() => setConfirmSubmit(true)}
                            disabled={processing}
                            className="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500 disabled:opacity-25"
                        >
                            <SendIcon />
                            Submit
                        </button>
                    )}
                </div>
            </div>

            {isEdit && (
                <ConfirmDialog
                    show={confirmSubmit}
                    title="Submit request?"
                    message={`Submit ${stockRequest.transaction_no} for approval? Your changes are saved first, and you will not be able to edit it afterwards.`}
                    confirmLabel="Submit"
                    processing={processing}
                    onConfirm={submitRequest}
                    onClose={() => !processing && setConfirmSubmit(false)}
                />
            )}
        </form>
    );
}
