import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, admin = false }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route(admin ? 'auth.adminlogin.store' : 'auth.login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={admin ? 'Admin Log In' : 'Log In'} />

            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Log In</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {admin ? 'Welcome to OSTR Admin Portal.' : 'Welcome to OSTR.'}{' '}
                Please sign in to continue.
            </p>

            {status && (
                <div className="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8">
                <div>
                    <InputLabel htmlFor="username" value="Username" />

                    <TextInput
                        id="username"
                        name="username"
                        value={data.username}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        placeholder="Enter your username"
                        isFocused={true}
                        onChange={(e) => setData('username', e.target.value)}
                    />

                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <InputLabel htmlFor="password" value="Password" />

                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-brand-600 hover:text-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:text-brand-300 dark:hover:text-brand-100 dark:focus:ring-offset-gray-950"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <PrimaryButton
                    className="mt-6 w-full justify-center"
                    disabled={processing}
                >
                    Log In
                </PrimaryButton>
            </form>

            {admin && (
                <p className="mt-6 text-center text-sm font-medium text-red-600 dark:text-red-400">
                    This page is for administrators only.
                </p>
            )}
        </GuestLayout>
    );
}
