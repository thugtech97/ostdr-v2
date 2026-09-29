import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        username: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Forgot Password
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Enter your username and we'll ask IT to reset your password.
            </p>

            {status && (
                <div className="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8">
                <InputLabel htmlFor="username" value="Username" />

                <TextInput
                    id="username"
                    name="username"
                    value={data.username}
                    className="mt-1 block w-full"
                    autoComplete="username"
                    isFocused={true}
                    onChange={(e) => setData('username', e.target.value)}
                />

                <InputError message={errors.username} className="mt-2" />

                <div className="mt-6 flex items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        Send Request
                    </PrimaryButton>

                    <Link
                        href={route('login')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-950"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
