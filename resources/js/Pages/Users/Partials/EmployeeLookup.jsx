import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import axios from 'axios';
import { useEffect, useState } from 'react';

/**
 * Searches the HR master and hands the picked employee to onSelect.
 */
export default function EmployeeLookup({ onSelect }) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);
            setError(null);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await axios.get(route('users.employees'), {
                    params: { search: term.trim() },
                    signal: controller.signal,
                });
                setResults(response.data);
                setOpen(true);
            } catch (e) {
                if (!axios.isCancel(e)) {
                    setResults([]);
                    setError(
                        e.response?.data?.message ??
                            'Employee search failed. You can still fill in the details manually.',
                    );
                }
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const pick = (employee) => {
        onSelect(employee);
        setTerm('');
        setResults([]);
        setOpen(false);
    };

    return (
        <div className="relative">
            <InputLabel htmlFor="employee" value="Find employee (HRIS)" />
            <TextInput
                id="employee"
                type="search"
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                onFocus={() => results.length && setOpen(true)}
                placeholder="Type a name or employee ID..."
                className="mt-1 block w-full"
                autoComplete="off"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {loading ? 'Searching...' : 'Picking an employee fills in the name and suggests a username.'}
            </p>
            {error && <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">{error}</p>}

            {open && results.length > 0 && (
                <ul
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                >
                    {results.map((employee) => (
                        <li key={employee.emp_id + employee.department}>
                            <button
                                type="button"
                                onClick={() => pick(employee)}
                                className="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <span className="block font-medium text-gray-900 dark:text-gray-100">{employee.name}</span>
                                <span className="block text-xs text-gray-500 dark:text-gray-400">
                                    {employee.emp_id}
                                    {employee.department ? ` · ${employee.department}` : ''}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {open && !loading && !error && term.trim().length >= 2 && results.length === 0 && (
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">No active employees found.</p>
            )}
        </div>
    );
}
