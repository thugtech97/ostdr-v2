import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

export default function ScheduleForm({ schedule = null }) {
    const isEdit = schedule !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        scheduled_date: schedule?.scheduled_date ?? '',
        scheduled_time: schedule?.scheduled_time ?? '',
        reason: schedule?.reason ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        isEdit ? put(route('applications.update', schedule.id)) : post(route('applications.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card className="space-y-5 p-6">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="scheduled_date" value="Scheduled Date *" />
                        <TextInput
                            id="scheduled_date"
                            type="date"
                            value={data.scheduled_date}
                            onChange={(e) => setData('scheduled_date', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        />
                        <InputError message={errors.scheduled_date} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="scheduled_time" value="Scheduled Time *" />
                        <TextInput
                            id="scheduled_time"
                            type="time"
                            value={data.scheduled_time}
                            onChange={(e) => setData('scheduled_time', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        />
                        <InputError message={errors.scheduled_time} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="reason" value="Reason *" />
                    <textarea
                        id="reason"
                        rows={3}
                        value={data.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                        maxLength={190}
                        required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Shown to every user in the shutdown banner.</p>
                    <InputError message={errors.reason} className="mt-2" />
                </div>
            </Card>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{isEdit ? 'Save changes' : 'Create schedule'}</PrimaryButton>
                <Link href={route('applications.index')} className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                    Cancel
                </Link>
            </div>
        </form>
    );
}
