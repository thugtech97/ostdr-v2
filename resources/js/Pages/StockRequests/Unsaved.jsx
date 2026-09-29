import { DownloadIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RequestList from './Partials/RequestList';

export default function Unsaved({ requests, filters, can }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Stock Request' }, { label: 'Unsaved Requests' }]}
                    title="Unsaved Requests"
                    subtitle={
                        requests.total
                            ? `${requests.total} draft${requests.total === 1 ? '' : 's'} not yet submitted`
                            : 'Requests you started but never saved.'
                    }
                    actions={
                        requests.total > 0 && (
                            <a
                                href={route('stockrequests.export', { ...filters, unsaved: 1 })}
                                className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <DownloadIcon />
                                Export
                            </a>
                        )
                    }
                />
            }
        >
            <Head title="Unsaved Requests" />

            <div className="p-4 sm:p-6 lg:p-8">
                <RequestList requests={requests} filters={filters} can={can} routeName="stockrequests.unsaved" unsaved />
            </div>
        </AuthenticatedLayout>
    );
}
