import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import EmployeeLookup from './EmployeeLookup';

const PASSWORD_HINT = 'At least 8 characters, with an upper case letter, a lower case letter, a number and a special character.';

export default function UserForm({ user = null, roles, departments }) {
    const isEdit = user !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        username: user?.username ?? '',
        dept: user?.dept ?? '',
        email: user?.email ?? '',
        role_id: user?.role_id ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        isEdit ? put(route('users.update', user.id)) : post(route('users.store'));
    };

    const fillFromEmployee = (employee) =>
        setData((current) => ({
            ...current,
            name: employee.name,
            username: employee.username,
        }));

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card className="p-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Account details</h2>

                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                    {!isEdit && (
                        <div className="sm:col-span-2">
                            <EmployeeLookup onSelect={fillFromEmployee} />
                        </div>
                    )}

                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="name" value="Name *" />
                        <TextInput
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="username" value="Username *" />
                        <TextInput
                            id="username"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value.toUpperCase())}
                            className="mt-1 block w-full font-mono"
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.username} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email ?? ''}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="dept" value="Department / Satellite *" />
                        <SelectInput
                            id="dept"
                            value={data.dept}
                            onChange={(e) => setData('dept', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        >
                            <option value="" disabled>
                                Select a department
                            </option>
                            {departments.map((dept) => (
                                <option key={dept} value={dept}>
                                    {dept}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={errors.dept} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="role_id" value="Role *" />
                        <SelectInput
                            id="role_id"
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        >
                            <option value="" disabled>
                                Select a role
                            </option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                    {role.active ? '' : ' (inactive)'}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={errors.role_id} className="mt-2" />
                    </div>
                </div>
            </Card>

            <Card className="p-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {isEdit ? 'Reset password' : 'Initial password'}
                </h2>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {isEdit
                        ? 'Leave blank to keep the current password.'
                        : 'Share this with the user; they can change it after logging in.'}{' '}
                    {PASSWORD_HINT}
                </p>

                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="password" value={isEdit ? 'New password' : 'Password *'} />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            required={!isEdit}
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            required={!isEdit || data.password !== ''}
                        />
                    </div>
                </div>
            </Card>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{isEdit ? 'Save changes' : 'Create user'}</PrimaryButton>
                <Link
                    href={route('users.index')}
                    className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
