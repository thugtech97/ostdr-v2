import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

export default function ConfirmDialog({
    show,
    title,
    message,
    confirmLabel = 'Confirm',
    danger = false,
    processing = false,
    onConfirm,
    onClose,
}) {
    const ConfirmButton = danger ? DangerButton : PrimaryButton;

    return (
        <Modal show={show} maxWidth="md" onClose={onClose}>
            <div className="p-6 dark:bg-gray-900">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{message}</p>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={processing}>
                        Cancel
                    </SecondaryButton>
                    <ConfirmButton onClick={onConfirm} disabled={processing}>
                        {confirmLabel}
                    </ConfirmButton>
                </div>
            </div>
        </Modal>
    );
}
