import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import TextInput from '@/Components/TextInput';
import { useMemo, useState } from 'react';

/**
 * A small maintenance list filtered in the browser (legacy's global filter).
 * `fields` are the row keys searched; `children(rows)` renders the visible rows.
 */
export default function SearchableList({ rows, fields, headers, noun, children }) {
    const [search, setSearch] = useState('');

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term ? rows.filter((row) => fields.some((field) => String(row[field] ?? '').toLowerCase().includes(term))) : rows;
    }, [rows, fields, search]);

    return (
        <Card>
            <div className="border-b border-gray-200 p-4 dark:border-gray-800">
                <TextInput
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={`Search ${noun}...`}
                    className="w-full sm:max-w-sm"
                    aria-label={`Search ${noun}`}
                />
            </div>

            <DataTable headers={headers} count={visible.length} empty={search ? `No ${noun} match your search.` : `No ${noun} found.`}>
                {children(visible)}
            </DataTable>
        </Card>
    );
}
