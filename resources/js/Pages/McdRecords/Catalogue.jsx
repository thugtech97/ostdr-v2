import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import { DownloadIcon, UploadIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * Update Items: the published catalogue, refreshed from the MCD stock list workbook.
 */
export default function Catalogue({ products, filters, can }) {
    const list = useListQuery('mcdrecords.update-items', filters, { defaultSort: 'code', defaultDirection: 'asc' });
    const fileInput = useRef(null);
    const { errors } = usePage().props;
    const [processing, setProcessing] = useState(false);

    // Picking a file uploads it straight away, as legacy does.
    const upload = (file) => {
        if (!file) {
            return;
        }

        router.post(route('mcdrecords.upload-stocks'), { itemFile: file }, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records' }, { label: 'Update Items' }]}
                    title="Catalogue Items"
                    subtitle={products.total ? `${products.total} published item${products.total === 1 ? '' : 's'}` : 'Published catalogue items.'}
                    actions={
                        <>
                            {products.total > 0 && (
                                <ButtonLink href={route('mcdrecords.update-items.export', filters)} variant="secondary" plain>
                                    <DownloadIcon />
                                    Export
                                </ButtonLink>
                            )}
                            {can.upload && (
                                <>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        accept=".xlsx,.xls"
                                        className="hidden"
                                        onChange={(e) => upload(e.target.files[0])}
                                    />
                                    <PrimaryButton onClick={() => fileInput.current?.click()} disabled={processing} className="gap-1.5">
                                        <UploadIcon />
                                        {processing ? 'Updating items...' : 'Import XLSX'}
                                    </PrimaryButton>
                                </>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title="Update Items" />

            <div className="space-y-4 p-4 sm:p-6 lg:p-8">
                {errors.itemFile && (
                    <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-4 text-sm dark:border-red-900 dark:bg-red-950">
                        <p className="font-medium text-red-800 dark:text-red-200">Whoops! Something didn't work.</p>
                        <InputError message={errors.itemFile} className="mt-1" />
                    </div>
                )}

                <Card>
                    <ListToolbar
                        routeName="mcdrecords.update-items"
                        filters={filters}
                        list={list}
                        perPage={products.per_page}
                        dates={false}
                        placeholder="Search code, description, UoM, OEM..."
                    />

                    <DataTable
                        headers={[
                            { column: 'code', label: 'Stock Code' },
                            { column: 'name', label: 'Description' },
                            { column: 'uom', label: 'UoM' },
                            { column: 'oem', label: 'OEM' },
                        ]}
                        list={list}
                        count={products.data.length}
                        empty={filters.search ? 'No items match your search.' : 'No published items.'}
                    >
                        {products.data.map((product) => (
                            <DataTable.Row key={product.id}>
                                <DataTable.Cell strong className="font-mono text-xs">{product.code}</DataTable.Cell>
                                <DataTable.Cell wrap>{product.name}</DataTable.Cell>
                                <DataTable.Cell>{product.uom}</DataTable.Cell>
                                <DataTable.Cell>{product.oem || '—'}</DataTable.Cell>
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={products} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
