import DataTable from '@/Components/DataTable';
import LogReport from './Partials/LogReport';

export default function ErrorLogs({ logs, filters }) {
    return (
        <LogReport
            title="Error Logs"
            crumb="Application Error Logs"
            routeName="reports.error-logs"
            exportRoute="reports.error-logs.export"
            logs={logs}
            filters={filters}
            placeholder="Search message, level, user..."
            empty="No errors logged for this period."
            headers={[
                { column: 'id', label: 'ID' },
                { column: 'message', label: 'Message' },
                { column: 'level', label: 'Level' },
                { column: 'level_name', label: 'Level Name' },
                { label: 'User' },
                { column: 'created_at', label: 'Date' },
            ]}
        >
            {(rows) =>
                rows.map((row) => (
                    <DataTable.Row key={row.id} className="align-top">
                        <DataTable.Cell className="tabular-nums">{row.id}</DataTable.Cell>
                        <DataTable.Cell wrap className="max-w-xl break-words">{row.message}</DataTable.Cell>
                        <DataTable.Cell className="tabular-nums">{row.level}</DataTable.Cell>
                        <DataTable.Cell strong>{row.level_name}</DataTable.Cell>
                        <DataTable.Cell>{row.username || '—'}</DataTable.Cell>
                        <DataTable.Cell>{row.datetime}</DataTable.Cell>
                    </DataTable.Row>
                ))
            }
        </LogReport>
    );
}
