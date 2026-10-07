import TextInput from '@/Components/TextInput';
import { useMemo, useState } from 'react';

/**
 * Filterable multi-select of stock requests (legacy MultiSelect on transaction #).
 * `requests` are { transaction_no, dept }; `value` is the selected transaction numbers.
 */
export default function RequestPicker({ requests, value, onChange, disabled = false }) {
    const [filter, setFilter] = useState('');

    const visible = useMemo(() => {
        const term = filter.trim().toLowerCase();

        return term
            ? requests.filter((request) => `${request.transaction_no} ${request.dept ?? ''}`.toLowerCase().includes(term))
            : requests;
    }, [requests, filter]);

    const toggle = (transactionNo) =>
        onChange(value.includes(transactionNo) ? value.filter((no) => no !== transactionNo) : [...value, transactionNo]);

    return (
        <div className="rounded-md border border-gray-300 dark:border-gray-700">
            <div className="flex items-center gap-2 border-b border-gray-200 p-2 dark:border-gray-800">
                <TextInput
                    type="search"
                    value={filter}
                    onChange={(e) => setFilter(e.target.value)}
                    placeholder="Filter stock requests..."
                    className="w-full py-1.5 text-sm"
                    disabled={disabled}
                />
                <span className="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{value.length} selected</span>
            </div>
            <ul className="max-h-56 overflow-y-auto p-1 text-sm">
                {visible.length === 0 && <li className="px-2 py-4 text-center text-gray-500 dark:text-gray-400">No stock requests to batch.</li>}
                {visible.map((request) => (
                    <li key={request.transaction_no}>
                        <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <input
                                type="checkbox"
                                checked={value.includes(request.transaction_no)}
                                onChange={() => toggle(request.transaction_no)}
                                disabled={disabled}
                                className="rounded border-gray-300 text-brand-900 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                            <span className="font-medium text-gray-900 dark:text-gray-100">{request.transaction_no}</span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">{request.dept}</span>
                        </label>
                    </li>
                ))}
            </ul>
        </div>
    );
}
