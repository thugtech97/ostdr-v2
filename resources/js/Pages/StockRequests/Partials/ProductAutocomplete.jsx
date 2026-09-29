import TextInput from '@/Components/TextInput';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

/**
 * Catalogue lookup by stock code or item name (starts-with, like legacy). Calls onSelect(product).
 */
export default function ProductAutocomplete({ field, value, onChange, onSelect, placeholder, id, className = '' }) {
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const typed = useRef(false);

    useEffect(() => {
        const term = value.trim();

        if (!typed.current || term.length === 0) {
            setResults([]);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await axios.get(route('products.search'), {
                    params: { field, q: term },
                    signal: controller.signal,
                });
                setResults(response.data);
                setHighlight(0);
                setOpen(true);
            } catch (e) {
                if (!axios.isCancel(e)) {
                    setResults([]);
                }
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [value, field]);

    const pick = (product) => {
        typed.current = false;
        setOpen(false);
        setResults([]);
        onSelect(product);
    };

    const onKeyDown = (e) => {
        if (!open || results.length === 0) {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlight((h) => Math.min(h + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlight((h) => Math.max(h - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            pick(results[highlight]);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div className={'relative ' + className}>
            <TextInput
                id={id}
                value={value}
                onChange={(e) => {
                    typed.current = true;
                    onChange(e.target.value);
                }}
                onKeyDown={onKeyDown}
                onFocus={() => results.length > 0 && setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                placeholder={placeholder}
                className="block w-full"
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
            />
            {loading && <span className="absolute right-3 top-2.5 text-xs text-gray-400">…</span>}

            {open && results.length > 0 && (
                <ul
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-72 w-full min-w-[20rem] overflow-y-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                >
                    {results.map((product, index) => (
                        <li key={product.code} role="option" aria-selected={index === highlight}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => pick(product)}
                                className={
                                    'block w-full px-3 py-2 text-left ' +
                                    (index === highlight ? 'bg-brand-50 dark:bg-gray-700' : 'hover:bg-gray-100 dark:hover:bg-gray-700')
                                }
                            >
                                <span className="font-mono text-xs text-gray-500 dark:text-gray-400">{product.code}</span>{' '}
                                <span className="text-gray-900 dark:text-gray-100">{product.name}</span>
                                <span className="ml-1 text-xs text-gray-500 dark:text-gray-400">({product.uom})</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {open && !loading && typed.current && value.trim() !== '' && results.length === 0 && (
                <p className="absolute z-30 mt-1 w-full rounded-md bg-white px-3 py-2 text-sm text-gray-500 shadow ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700">
                    No items found.
                </p>
            )}
        </div>
    );
}
