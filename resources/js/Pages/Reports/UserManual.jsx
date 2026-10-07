import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import { DownloadIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function UserManual({ url }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'OSTR' }, { label: 'User Manual' }]}
                    title="User Manual"
                    actions={
                        <ButtonLink href={url} variant="secondary" plain target="_blank" rel="noopener">
                            <DownloadIcon />
                            Open PDF
                        </ButtonLink>
                    }
                />
            }
        >
            <Head title="User Manual" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <iframe src={url} title="OSTR User Manual" className="h-[75vh] w-full border-0" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
