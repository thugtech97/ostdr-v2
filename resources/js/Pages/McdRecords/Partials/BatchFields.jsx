import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

const required = <span className="text-red-600"> *</span>;

/**
 * Batch No + Description, shared by the create / edit / view screens.
 */
export default function BatchFields({ data, setData, errors = {}, readOnly = false }) {
    return (
        <div className="grid gap-5 lg:max-w-xl">
            <div>
                <InputLabel htmlFor="batchno">Batch No{!readOnly && required}</InputLabel>
                <TextInput
                    id="batchno"
                    value={data.batchno}
                    onChange={(e) => setData('batchno', e.target.value)}
                    className="mt-1 block w-full"
                    maxLength={50}
                    disabled={readOnly}
                />
                <InputError message={errors.batchno} className="mt-1" />
            </div>
            <div>
                <InputLabel htmlFor="description">Description{!readOnly && required}</InputLabel>
                <textarea
                    id="description"
                    rows={2}
                    value={data.description ?? ''}
                    onChange={(e) => setData('description', e.target.value)}
                    maxLength={255}
                    disabled={readOnly}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:disabled:bg-gray-800"
                />
                <InputError message={errors.description} className="mt-1" />
            </div>
        </div>
    );
}
