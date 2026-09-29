import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout header={<PageHeader title="Dashboard" subtitle={`Welcome, ${user.name}.`} />}>
            <Head title="Dashboard" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card className="p-6 text-sm text-gray-600 dark:text-gray-400">
                    The stock request dashboard will appear here once that module is built in v2.
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
