import Card from '@/Components/Card';
import IconButton from '@/Components/IconButton';
import { PencilIcon, PlusIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Index({ roles, can }) {
    const [search, setSearch] = useState('');

    // The role list is small, so it is filtered in the browser.
    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term
            ? roles.filter((role) =>
                  [role.name, role.description].some((value) => value?.toLowerCase().includes(term)),
              )
            : roles;
    }, [roles, search]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Roles' }]}
                    title="Roles"
                    subtitle={`${roles.length} role${roles.length === 1 ? '' : 's'} defined`}
                    actions={
                        can.create && (
                            <Link
                                href={route('roles.create')}
                                className="inline-flex items-center gap-1.5 rounded-md bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500"
                            >
                                <PlusIcon />
                                New Role
                            </Link>
                        )
                    }
                />
            }
        >
            <Head title="Roles" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <div className="border-b border-gray-200 p-4 dark:border-gray-800">
                        <TextInput
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search roles..."
                            className="w-full sm:max-w-sm"
                            aria-label="Search roles"
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" className="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                    <th scope="col" className="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Description</th>
                                    <th scope="col" className="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Users</th>
                                    <th scope="col" className="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                    {can.edit && (
                                        <th scope="col" className="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {visible.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            {search ? 'No roles match your search.' : 'No roles found.'}
                                        </td>
                                    </tr>
                                )}

                                {visible.map((role) => (
                                    <tr key={role.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{role.name}</td>
                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{role.description}</td>
                                        <td className="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{role.users_count}</td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <StatusBadge active={role.active} />
                                        </td>
                                        {can.edit && (
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <IconButton label="Edit role" href={route('roles.edit', role.id)}>
                                                    <PencilIcon />
                                                </IconButton>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
