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

/**
 * Shared shell for the audit and error logs: date range (today by default), search, table.
 */
export default function LogReport({ title, crumb, routeName, exportRoute, logs, filters, headers, placeholder, empty, children }) {
    const list = useListQuery(routeName, filters, { defaultSort: 'created_at' });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: crumb }]}
                    title={title}
                    subtitle={`${logs.total} entr${logs.total === 1 ? 'y' : 'ies'} from ${filters.date_from} to ${filters.date_to}`}
                    actions={
                        logs.total > 0 && (
                            <ButtonLink href={route(exportRoute, filters)} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title={crumb} />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar routeName={routeName} filters={filters} list={list} perPage={logs.per_page} placeholder={placeholder} />

                    <DataTable headers={headers} list={list} count={logs.data.length} empty={empty}>
                        {children(logs.data)}
                    </DataTable>

                    <Pagination paginator={logs} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
