import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MaintenanceForm from '@/Components/MaintenanceForm';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

export default function PermissionForm({ permission = null, modules }) {
    const isEdit = permission !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        module_type: permission?.module_type ?? '',
        description: permission?.description ?? '',
        active: permission?.active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();

        isEdit ? put(route('permissions.update', permission.id)) : post(route('permissions.store'));
    };

    return (
        <MaintenanceForm
            onSubmit={submit}
            active={data.active}
            onActiveChange={(value) => setData('active', value)}
            processing={processing}
            submitLabel={isEdit ? 'Save changes' : 'Create permission'}
            cancelHref={route('permissions.index')}
        >
            <div>
                <InputLabel htmlFor="module_type" value="Module *" />
                <SelectInput
                    id="module_type"
                    value={data.module_type}
                    onChange={(e) => setData('module_type', e.target.value)}
                    className="mt-1 block w-full"
                    required
                >
                    <option value="">Select module</option>
                    {modules.map((module) => (
                        <option key={module} value={module}>
                            {module}
                        </option>
                    ))}
                </SelectInput>
                <InputError message={errors.module_type} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="description" value="Permission Description *" />
                <TextInput
                    id="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="mt-1 block w-full"
                    maxLength={190}
                    required
                />
                {isEdit && (
                    <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        The app grants access by this name. Renaming a built-in page (e.g. "Manage Receiving") locks everyone but admins out of it.
                    </p>
                )}
                <InputError message={errors.description} className="mt-2" />
            </div>
        </MaintenanceForm>
    );
}
