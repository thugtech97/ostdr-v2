import DataTable from '@/Components/DataTable';
import LogReport from './Partials/LogReport';

function Values({ json }) {
    return json ? <code className="block max-w-xs whitespace-pre-wrap break-all text-xs text-gray-600 dark:text-gray-400">{json}</code> : '—';
}

export default function AuditLogs({ logs, filters }) {
    return (
        <LogReport
            title="Audit Logs"
            crumb="User Action Monitoring"
            routeName="reports.audit-logs"
            exportRoute="reports.audit-logs.export"
            logs={logs}
            filters={filters}
            placeholder="Search user, action, model..."
            empty="No audit entries for this period."
            headers={[
                { column: 'auditable_type', label: 'Model' },
                { column: 'event', label: 'Action' },
                { column: 'username', label: 'User' },
                { label: 'Old Value' },
                { label: 'New Value' },
                { column: 'created_at', label: 'Activity Date' },
            ]}
        >
            {(rows) =>
                rows.map((row) => (
                    <DataTable.Row key={row.id} className="align-top">
                        <DataTable.Cell>
                            {row.auditable_type || '—'}
                            {row.auditable_id ? <span className="block text-xs text-gray-500">#{row.auditable_id}</span> : null}
                        </DataTable.Cell>
                        <DataTable.Cell strong>{row.event}</DataTable.Cell>
                        <DataTable.Cell>{row.username || '—'}</DataTable.Cell>
                        <DataTable.Cell wrap>
                            <Values json={row.old_values} />
                        </DataTable.Cell>
                        <DataTable.Cell wrap>
                            <Values json={row.new_values} />
                        </DataTable.Cell>
                        <DataTable.Cell>{row.created_at}</DataTable.Cell>
                    </DataTable.Row>
                ))
            }
        </LogReport>
    );
}
