import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { XIcon } from '@/Components/Icons';

/**
 * Items of a batch (or candidates for one). Pass `selected` + `onSelect` for the checkbox
 * column, or `onExclude` for the exclude action.
 */
export default function BatchItemsTable({ title = 'Items', items, selected, onSelect, onExclude, empty = 'No record found.' }) {
    const selectable = Array.isArray(selected);
    const allChecked = selectable && items.length > 0 && items.every((item) => selected.includes(item.id));

    const headers = [
        ...(selectable ? [{ label: 'select' }] : []),
        { label: 'Transaction #' },
        { label: 'Stock Code' },
        { label: 'Description' },
        { label: 'Requested Qty' },
        { label: 'Issued Qty' },
        { label: 'Remarks' },
        ...(onExclude ? [{ label: 'Actions', align: 'right' }] : []),
    ];

    return (
        <Card>
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {selectable ? `${selected.length} of ${items.length} checked` : `${items.length} item${items.length === 1 ? '' : 's'}`}
                </span>
            </div>
            <DataTable
                headers={headers.map((header) =>
                    header.label === 'select'
                        ? {
                              label: (
                                  <input
                                      type="checkbox"
                                      aria-label="Check all items"
                                      checked={allChecked}
                                      onChange={(e) => onSelect(e.target.checked ? items.map((item) => item.id) : [])}
                                      className="rounded border-gray-300 text-brand-900 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                                  />
                              ),
                          }
                        : header,
                )}
                count={items.length}
                empty={empty}
            >
                {items.map((item) => (
                    <DataTable.Row key={item.id}>
                        {selectable && (
                            <DataTable.Cell>
                                <input
                                    type="checkbox"
                                    aria-label={`Check ${item.stock_code}`}
                                    checked={selected.includes(item.id)}
                                    onChange={(e) =>
                                        onSelect(e.target.checked ? [...selected, item.id] : selected.filter((id) => id !== item.id))
                                    }
                                    className="rounded border-gray-300 text-brand-900 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                                />
                            </DataTable.Cell>
                        )}
                        <DataTable.Cell strong>{item.transaction_no}</DataTable.Cell>
                        <DataTable.Cell className="font-mono text-xs">{item.stock_code}</DataTable.Cell>
                        <DataTable.Cell wrap>{item.description}</DataTable.Cell>
                        <DataTable.Cell className="tabular-nums">{item.requested_qty}</DataTable.Cell>
                        <DataTable.Cell className="tabular-nums">{item.issued_qty}</DataTable.Cell>
                        <DataTable.Cell wrap>{item.remarks}</DataTable.Cell>
                        {onExclude && (
                            <DataTable.Actions>
                                <IconButton label="Exclude from batch" tone="danger" onClick={() => onExclude(item)}>
                                    <XIcon />
                                </IconButton>
                            </DataTable.Actions>
                        )}
                    </DataTable.Row>
                ))}
            </DataTable>
        </Card>
    );
}
