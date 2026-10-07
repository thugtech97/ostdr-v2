import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import { DownloadIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head } from '@inertiajs/react';

const REPORTS = {
    unbatched: {
        title: 'Unbatched Items',
        route: 'mcdrecords.unbatched',
        subtitle: 'Open items not yet assigned to a batch.',
        empty: 'Every open item is batched.',
    },
    unserved: {
        title: 'Unserved Items',
        route: 'mcdrecords.unserved',
        subtitle: 'Open items nothing has been issued against.',
        empty: 'No unserved items.',
    },
};

/**
 * Unbatched Items and Unserved Items: the same requested-item table over two filters.
 */
export default function ItemReport({ report, items, filters }) {
    const config = REPORTS[report];
    const list = useListQuery(config.route, filters, { defaultSort: null });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records' }, { label: config.title }]}
                    title={config.title}
                    subtitle={items.total ? `${items.total} item${items.total === 1 ? '' : 's'}` : config.subtitle}
                    actions={
                        items.total > 0 && (
                            <ButtonLink href={route('mcdrecords.items.export', { report, ...filters })} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title={config.title} />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName={config.route}
                        filters={filters}
                        list={list}
                        perPage={items.per_page}
                        dates={false}
                        placeholder="Search transaction #, stock code, description..."
                    />

                    <DataTable
                        headers={[
                            { column: 'transaction_no', label: 'Transaction No' },
                            { column: 'stock_code', label: 'Stock Code' },
                            { column: 'description', label: 'Description' },
                            { column: 'requested_qty', label: 'Requested Qty' },
                            { column: 'issued_qty', label: 'Issued Qty' },
                            { column: 'remarks', label: 'Remarks' },
                        ]}
                        list={list}
                        count={items.data.length}
                        empty={filters.search ? 'No items match your search.' : config.empty}
                    >
                        {items.data.map((item) => (
                            <DataTable.Row key={item.id}>
                                <DataTable.Cell strong>{item.transaction_no}</DataTable.Cell>
                                <DataTable.Cell className="font-mono text-xs">{item.stock_code}</DataTable.Cell>
                                <DataTable.Cell wrap>{item.description}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.requested_qty}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.issued_qty}</DataTable.Cell>
                                <DataTable.Cell wrap>{item.remarks}</DataTable.Cell>
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={items} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
