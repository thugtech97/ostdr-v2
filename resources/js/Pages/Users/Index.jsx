import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import IconButton from '@/Components/IconButton';
import { PencilIcon, PlusIcon, UserCheckIcon, UserXIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const COLUMNS = [
    { key: 'name', label: 'Name' },
    { key: 'username', label: 'Username' },
    { key: 'dept', label: 'Department' },
    { key: 'role', label: 'Role' },
    { key: 'email', label: 'Email' },
    { key: 'isActive', label: 'Status' },
];

export default function Index({ users, filters, can }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);
    const firstRender = useRef(true);

    const sort = filters.sort ?? 'name';
    const direction = filters.direction ?? 'asc';

    const reload = (params) =>
        router.get(
            route('users.index'),
            { ...filters, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Debounced server-side search; the first render already has the results.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => reload({ search: search || undefined, page: undefined }), 300);

        return () => clearTimeout(timer);
    }, [search]);

    const toggleSort = (key) =>
        reload({
            sort: key,
            direction: sort === key && direction === 'asc' ? 'desc' : 'asc',
            page: undefined,
        });

    const confirmStatusChange = () => {
        router.patch(
            route(pending.activate ? 'users.activate' : 'users.deactivate', pending.user.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Users' }]}
                    title="Users"
                    subtitle={`${users.total} user${users.total === 1 ? '' : 's'}${filters.search ? ' matching your search' : ''}`}
                    actions={
                        can.create && (
                            <Link
                                href={route('users.create')}
                                className="inline-flex items-center gap-1.5 rounded-md bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500"
                            >
                                <PlusIcon />
                                New User
                            </Link>
                        )
                    }
                />
            }
        >
            <Head title="Users" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <div className="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                        <TextInput
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search name, username, department..."
                            className="w-full sm:max-w-sm"
                            aria-label="Search users"
                        />

                        <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            Rows
                            <SelectInput
                                value={users.per_page}
                                onChange={(e) => reload({ per_page: e.target.value, page: undefined })}
                                className="py-1.5 text-sm"
                            >
                                {[10, 20, 50].map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </SelectInput>
                        </label>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    {COLUMNS.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            aria-sort={
                                                sort === column.key
                                                    ? direction === 'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : undefined
                                            }
                                            className="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => toggleSort(column.key)}
                                                className="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                            >
                                                {column.label}
                                                <span className="text-xs text-gray-400" aria-hidden="true">
                                                    {sort === column.key ? (direction === 'asc' ? '▲' : '▼') : '↕'}
                                                </span>
                                            </button>
                                        </th>
                                    ))}
                                    {can.edit && (
                                        <th scope="col" className="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={COLUMNS.length + 1} className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            {filters.search ? 'No users match your search.' : 'No users found.'}
                                        </td>
                                    </tr>
                                )}

                                {users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {user.name}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                            {user.username}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{user.dept}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{user.role}</td>
                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{user.email}</td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <StatusBadge active={user.isActive == 1} />
                                        </td>
                                        {can.edit && (
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <div className="inline-flex items-center gap-1">
                                                    <IconButton label="Edit user" href={route('users.edit', user.id)}>
                                                        <PencilIcon />
                                                    </IconButton>
                                                    {user.isActive == 1 ? (
                                                        <IconButton
                                                            label="Deactivate user"
                                                            tone="danger"
                                                            onClick={() => setPending({ user, activate: false })}
                                                        >
                                                            <UserXIcon />
                                                        </IconButton>
                                                    ) : (
                                                        <IconButton
                                                            label="Activate user"
                                                            tone="success"
                                                            onClick={() => setPending({ user, activate: true })}
                                                        >
                                                            <UserCheckIcon />
                                                        </IconButton>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Pagination paginator={users} />
                </Card>
            </div>

            <ConfirmDialog
                show={pending !== null}
                title={pending?.activate ? 'Activate user?' : 'Deactivate user?'}
                message={
                    pending?.activate
                        ? `${pending?.user.name} will be able to log in again.`
                        : `${pending?.user.name} will no longer be able to log in.`
                }
                confirmLabel={pending?.activate ? 'Activate' : 'Deactivate'}
                danger={!pending?.activate}
                processing={processing}
                onConfirm={confirmStatusChange}
                onClose={() => !processing && setPending(null)}
            />
        </AuthenticatedLayout>
    );
}
