import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MaintenanceForm from '@/Components/MaintenanceForm';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

export default function SatelliteForm({ satellite = null }) {
    const isEdit = satellite !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: satellite?.name ?? '',
        description: satellite?.description ?? '',
        active: satellite?.active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();

        isEdit ? put(route('satellites.update', satellite.id)) : post(route('satellites.store'));
    };

    return (
        <MaintenanceForm
            onSubmit={submit}
            active={data.active}
            onActiveChange={(value) => setData('active', value)}
            processing={processing}
            submitLabel={isEdit ? 'Save changes' : 'Create satellite'}
            cancelHref={route('satellites.index')}
        >
            <div>
                <InputLabel htmlFor="name" value="Satellite Name *" />
                <TextInput
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value.toUpperCase())}
                    className="mt-1 block w-full"
                    maxLength={190}
                    required
                />
                {isEdit && (
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Users and requests keep the department name as text, so a rename does not change existing records.
                    </p>
                )}
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="description" value="Satellite Description *" />
                <TextInput
                    id="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="mt-1 block w-full"
                    maxLength={190}
                    required
                />
                <InputError message={errors.description} className="mt-2" />
            </div>
        </MaintenanceForm>
    );
}
