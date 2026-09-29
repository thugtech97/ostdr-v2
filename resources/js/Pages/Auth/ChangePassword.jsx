import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ChangePassword({ status }) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => reset('current_password'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<PageHeader title="Change Password" />}
        >
            <Head title="Change Password" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div>
                    <div className="max-w-xl rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                        {status && (
                            <div className="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel
                                    htmlFor="current_password"
                                    value="Current Password"
                                />

                                <TextInput
                                    id="current_password"
                                    type="password"
                                    value={data.current_password}
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) =>
                                        setData(
                                            'current_password',
                                            e.target.value,
                                        )
                                    }
                                />

                                <InputError
                                    message={errors.current_password}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <InputLabel
                                        htmlFor="password"
                                        value="New Password"
                                    />

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword((shown) => !shown)
                                        }
                                        className="text-sm text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-100"
                                    >
                                        {showPassword
                                            ? 'Hide password'
                                            : 'Show password'}
                                    </button>
                                </div>

                                <TextInput
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />

                                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    At least 8 characters, with an upper case
                                    letter, a lower case letter, a number and a
                                    special character.
                                </p>

                                <InputError
                                    message={errors.password}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="password_confirmation"
                                    value="Confirm New Password"
                                />

                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                    className="mt-2"
                                />
                            </div>

                            <PrimaryButton disabled={processing}>
                                Save
                            </PrimaryButton>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
