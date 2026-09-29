import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

export default function RoleForm({ role = null, usersCount = 0 }) {
    const isEdit = role !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        active: role?.active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();

        isEdit ? put(route('roles.update', role.id)) : post(route('roles.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card className="space-y-5 p-6">
                <div>
                    <InputLabel htmlFor="name" value="Role name *" />
                    <TextInput
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value.toUpperCase())}
                        className="mt-1 block w-full"
                        maxLength={150}
                        required
                    />
                    {isEdit && usersCount > 0 && (
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Renaming updates the role shown on its {usersCount} user{usersCount === 1 ? '' : 's'}.
                        </p>
                    )}
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="description" value="Description *" />
                    <textarea
                        id="description"
                        rows={3}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        maxLength={190}
                        required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                    <InputError message={errors.description} className="mt-2" />
                </div>

                <label className="flex items-start gap-3">
                    <Checkbox
                        checked={data.active}
                        onChange={(e) => setData('active', e.target.checked)}
                        className="mt-0.5"
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-700 dark:text-gray-300">Active</span>
                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                            Only active roles can be assigned to users.
                        </span>
                    </span>
                </label>
            </Card>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{isEdit ? 'Save changes' : 'Create role'}</PrimaryButton>
                <Link
                    href={route('roles.index')}
                    className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
