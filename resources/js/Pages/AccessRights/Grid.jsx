import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const ACTIONS = [
    { key: 'view', label: 'View' },
    { key: 'create', label: 'Create' },
    { key: 'edit', label: 'Update' },
    { key: 'delete', label: 'Delete' },
    { key: 'print', label: 'Print' },
    { key: 'upload', label: 'Upload' },
];

const KINDS = {
    user: {
        title: 'List of Users Access Rights',
        crumb: 'User Access Rights',
        label: 'User',
        route: 'accessrights.user',
        param: 'user_id',
        save: 'accessrights.user.update',
        note: 'A user with no rights ticked uses their role’s access rights.',
    },
    role: {
        title: 'List of Roles Access Rights',
        crumb: 'Role Access Rights',
        label: 'Role',
        route: 'accessrights.role',
        param: 'role_id',
        save: 'accessrights.role.update',
        note: 'Users without access rights of their own get these.',
    },
};

function ModuleCheckbox({ checked, indeterminate, onChange, label, disabled }) {
    return (
        <input
            type="checkbox"
            aria-label={label}
            checked={checked}
            ref={(el) => el && (el.indeterminate = indeterminate)}
            onChange={(e) => onChange(e.target.checked)}
            disabled={disabled}
            className="rounded border-gray-300 text-brand-900 focus:ring-brand-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900"
        />
    );
}

/**
 * User / Role Access Rights. Ticking a module's column ticks that action for every
 * page in the module, as legacy does.
 */
export default function Grid({ kind, subjects, selected, modules, granted, canSave }) {
    const config = KINDS[kind];
    const [checked, setChecked] = useState(() => new Set(granted));
    const [processing, setProcessing] = useState(false);

    useEffect(() => setChecked(new Set(granted)), [granted, selected]);

    const dirty = useMemo(() => {
        const original = new Set(granted);
        return original.size !== checked.size || [...checked].some((key) => !original.has(key));
    }, [granted, checked]);

    const choose = (id) => router.get(route(config.route), id ? { [config.param]: id } : {}, { preserveScroll: true });

    const toggle = (keys, on) =>
        setChecked((current) => {
            const next = new Set(current);
            keys.forEach((key) => (on ? next.add(key) : next.delete(key)));
            return next;
        });

    const save = () =>
        router.put(
            route(config.save, selected),
            { grants: [...checked] },
            { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
        );

    const locked = !selected || !canSave || processing;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: config.crumb }]}
                    title={config.title}
                    subtitle={config.note}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <label htmlFor="subject" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {config.label}
                            </label>
                            <SelectInput id="subject" value={selected ?? ''} onChange={(e) => choose(e.target.value)} className="py-2 text-sm sm:w-72">
                                <option value="">Select {config.label.toLowerCase()}...</option>
                                {subjects.map((subject) => (
                                    <option key={subject.id} value={subject.id}>
                                        {subject.label}
                                        {subject.hint ? ` (${subject.hint})` : ''}
                                    </option>
                                ))}
                            </SelectInput>
                            {canSave && (
                                <PrimaryButton onClick={save} disabled={locked || !dirty}>
                                    Save Changes
                                </PrimaryButton>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={config.crumb} />

            <div className="p-4 sm:p-6 lg:p-8">
                {!selected && (
                    <p className="mb-4 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-900 dark:bg-gray-800 dark:text-gray-200">
                        Select a {config.label.toLowerCase()} to see and change their access rights.
                    </p>
                )}

                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" className="w-1/2 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">
                                        Module
                                    </th>
                                    {ACTIONS.map((action) => (
                                        <th key={action.key} scope="col" className="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">
                                            {action.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            {modules.map((module) => (
                                <tbody key={module.id} className="divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                                    <tr className="bg-gray-50/60 dark:bg-gray-800/30">
                                        <td className="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-900 dark:text-brand-200">
                                            {module.description}
                                        </td>
                                        {ACTIONS.map((action) => {
                                            const keys = module.permissions.map((permission) => `${permission.id}:${action.key}`);
                                            const count = keys.filter((key) => checked.has(key)).length;

                                            return (
                                                <td key={action.key} className="px-3 py-2 text-center">
                                                    <ModuleCheckbox
                                                        label={`${action.label} for all of ${module.description}`}
                                                        checked={count === keys.length}
                                                        indeterminate={count > 0 && count < keys.length}
                                                        onChange={(on) => toggle(keys, on)}
                                                        disabled={locked}
                                                    />
                                                </td>
                                            );
                                        })}
                                    </tr>
                                    {module.permissions.map((permission) => (
                                        <tr key={permission.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                            <td className="px-4 py-2 pl-8 text-gray-700 dark:text-gray-300">{permission.description}</td>
                                            {ACTIONS.map((action) => {
                                                const key = `${permission.id}:${action.key}`;

                                                return (
                                                    <td key={action.key} className="px-3 py-2 text-center">
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`${action.label} ${permission.description}`}
                                                            checked={checked.has(key)}
                                                            onChange={(e) => toggle([key], e.target.checked)}
                                                            disabled={locked}
                                                            className="rounded border-gray-300 text-brand-900 focus:ring-brand-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900"
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            ))}
                        </table>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
