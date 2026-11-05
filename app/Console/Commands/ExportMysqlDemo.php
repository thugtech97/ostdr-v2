<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Converts the SQL Server databases into MySQL dumps for hosts without SQL Server, such as a
 * cPanel demo. Every table's structure is kept, but the data is trimmed: see DATABASES and the
 * rowFilter() rules. The dumps hold real data and are git-ignored, so don't commit them.
 */
class ExportMysqlDemo extends Command
{
    protected $signature = 'ostr:export-mysql
        {--path=database/mysql-demo : Where to write the .sql files, relative to the project root}
        {--products=5000 : Latest catalogue products to keep on top of the ones requests use (0 = all)}
        {--audits=2000 : Latest audit rows to keep (0 = all)}
        {--demo-password= : Replace every user\'s password with this one instead of copying the hashes}';

    protected $description = 'Export the OSTR and catalogue databases as MySQL dumps for a demo deployment';

    /**
     * Tables in skip_data get their structure but no rows: runtime state, and data v2 never
     * reads that shouldn't leave the company network (old accounts and their password hashes).
     */
    private const DATABASES = [
        'ostdr' => [
            'connection' => 'sqlsrv',
            'skip_data' => ['users2', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
        ],
        'catalogue' => [
            'connection' => 'catalogue',
            'skip_data' => ['users', 'inventories', 'inventory_logs', 'inventory_uploads', 'product_photos'],
        ],
    ];

    /**
     * Indexes the app's lookups need on MySQL, on top of whatever the source table has.
     * Text columns take a prefix length so they stay within older InnoDB key limits.
     */
    private const EXTRA_INDEXES = [
        'ostdr' => [
            'stock_requests' => ['transaction_no'],
            'requested_items' => ['transaction_no', 'stock_code'],
            'issued_items' => ['item_id'],
            'roles_permissions' => ['role_id'],
            'users_permissions' => ['user_id'],
            'audits' => ['created_at', 'user_id'],
            'log' => ['created_at'],
            'sessions' => ['user_id', 'last_activity'],
        ],
        'catalogue' => [
            'products' => ['code(64)', 'name(100)'],
        ],
    ];

    /**
     * MySQL needs these keys although the SQL Server table has none: identity columns get
     * theirs automatically, and Laravel's session handler relies on sessions.id being unique.
     */
    private const EXTRA_PRIMARY_KEYS = ['ostdr' => ['sessions' => ['id']]];

    private const ROWS_PER_INSERT = 250;

    private const MAX_INSERT_BYTES = 512 * 1024;

    private ?string $demoPasswordHash = null;

    public function handle(): int
    {
        foreach (self::DATABASES as $config) {
            if (DB::connection($config['connection'])->getDriverName() !== 'sqlsrv') {
                $this->error("The {$config['connection']} connection must point at SQL Server to export from it.");

                return self::FAILURE;
            }
        }

        $directory = base_path($this->option('path'));
        File::ensureDirectoryExists($directory);

        foreach (self::DATABASES as $name => $config) {
            $path = "{$directory}/{$name}.sql";
            $this->components->info("Exporting {$name} to {$path}");
            $this->exportDatabase($name, DB::connection($config['connection']), $config['skip_data'], $path);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $skipData
     */
    private function exportDatabase(string $name, Connection $db, array $skipData, string $path): void
    {
        $file = fopen($path, 'wb');

        // No CREATE DATABASE or USE: cPanel prefixes database names, so the file is imported
        // into a database the user has already selected.
        fwrite($file, "-- OSTR {$name}: MySQL demo export of ".$db->getDatabaseName()."\n"
            ."-- Import into an empty database. Existing tables with these names are replaced.\n\n"
            ."SET NAMES utf8mb4;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\nSET AUTOCOMMIT = 0;\n\n");

        $summary = [];
        $tables = $db->select("SELECT t.name, SUM(p.rows) AS row_count
            FROM sys.tables t
            JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
            WHERE t.is_ms_shipped = 0
            GROUP BY t.name ORDER BY t.name");

        foreach ($tables as $table) {
            $columns = $this->columnsOf($db, $table->name);
            fwrite($file, $this->createTable($name, $db, $table->name, $columns));

            $exported = in_array($table->name, $skipData, true)
                ? 0
                : $this->writeRows($file, $name, $db, $table->name, $columns);

            $summary[] = [$table->name, $table->row_count, $exported];
        }

        fwrite($file, "COMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($file);

        $this->table(['Table', 'Source rows', 'Exported rows'], $summary);
    }

    /**
     * @return list<object>
     */
    private function columnsOf(Connection $db, string $table): array
    {
        return $db->select("SELECT c.name, ty.name AS type, c.max_length, c.precision, c.scale,
                c.is_nullable, c.is_identity, dc.definition AS default_value
            FROM sys.columns c
            JOIN sys.types ty ON ty.user_type_id = c.user_type_id
            LEFT JOIN sys.default_constraints dc ON dc.object_id = c.default_object_id
            WHERE c.object_id = OBJECT_ID(?)
            ORDER BY c.column_id", ["[dbo].[{$table}]"]);
    }

    /**
     * @param  list<object>  $columns
     */
    private function createTable(string $database, Connection $db, string $table, array $columns): string
    {
        $lines = array_map(fn ($column) => '  '.$this->columnDefinition($table, $column), $columns);

        $indexes = $db->select("SELECT i.name, i.is_primary_key, i.is_unique,
                STUFF((SELECT ',' + c.name FROM sys.index_columns ic
                    JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                    WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
                    ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 1, '') AS columns
            FROM sys.indexes i
            WHERE i.object_id = OBJECT_ID(?) AND i.index_id > 0", ["[dbo].[{$table}]"]);

        $primary = collect($indexes)->firstWhere('is_primary_key', 1)?->columns;
        $identity = collect($columns)->firstWhere('is_identity', 1)?->name;
        $primaryColumns = $primary !== null ? explode(',', $primary)
            : ($identity !== null ? [$identity] : (self::EXTRA_PRIMARY_KEYS[$database][$table] ?? null));

        if ($primaryColumns !== null) {
            $lines[] = '  PRIMARY KEY ('.implode(', ', array_map($this->quoteIndexColumn(...), $primaryColumns)).')';
        }

        foreach ($indexes as $index) {
            if (! $index->is_primary_key) {
                // Some catalogue indexes are named "table$index".
                $indexName = preg_replace('/^.*\$/', '', $index->name);
                $key = implode(', ', array_map($this->quoteIndexColumn(...), explode(',', $index->columns)));
                $lines[] = '  '.($index->is_unique ? 'UNIQUE ' : '')."KEY `{$indexName}` ({$key})";
            }
        }

        foreach (self::EXTRA_INDEXES[$database][$table] ?? [] as $column) {
            $indexName = $table.'_'.preg_replace('/\W.*$/', '', $column).'_index';
            $lines[] = "  KEY `{$indexName}` (".$this->quoteIndexColumn($column).')';
        }

        return "DROP TABLE IF EXISTS `{$table}`;\nCREATE TABLE `{$table}` (\n".implode(",\n", $lines)
            ."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
    }

    /**
     * Turns "code(64)" into "`code`(64)".
     */
    private function quoteIndexColumn(string $column): string
    {
        return preg_replace('/^(\w+)/', '`$1`', $column);
    }

    private function columnDefinition(string $table, object $column): string
    {
        $type = $this->mysqlType($table, $column);
        $definition = "`{$column->name}` {$type}".($column->is_nullable ? ' NULL' : ' NOT NULL');

        if ($column->is_identity) {
            return $definition.' AUTO_INCREMENT';
        }

        $default = $this->mysqlDefault($table, $column, $type);

        return $default === null ? $definition : "{$definition} DEFAULT {$default}";
    }

    private function mysqlType(string $table, object $column): string
    {
        $length = (int) $column->max_length;

        return match ($column->type) {
            'bigint', 'int', 'smallint' => strtoupper($column->type),
            // SQL Server's tinyint is 0-255.
            'tinyint' => 'TINYINT UNSIGNED',
            'bit' => 'TINYINT(1)',
            'money' => 'DECIMAL(19,4)',
            'decimal', 'numeric' => $column->is_identity ? 'BIGINT' : "DECIMAL({$column->precision},{$column->scale})",
            // max_length is in bytes, and nvarchar uses two per character. -1 means (max).
            'nvarchar', 'nchar' => $length === -1 ? 'LONGTEXT' : 'VARCHAR('.($length / 2).')',
            'varchar', 'char' => $length === -1 ? 'LONGTEXT' : "VARCHAR({$length})",
            'text', 'ntext' => 'LONGTEXT',
            'date' => 'DATE',
            'time' => 'TIME',
            'datetime', 'datetime2', 'smalldatetime' => 'DATETIME',
            default => throw new RuntimeException("No MySQL type for {$table}.{$column->name} ({$column->type})."),
        };
    }

    private function mysqlDefault(string $table, object $column, string $type): ?string
    {
        if ($column->default_value === null || $type === 'LONGTEXT') {
            return null;
        }

        $value = trim($column->default_value, '() ');

        return match (true) {
            strcasecmp($value, 'NULL') === 0 => 'NULL',
            strcasecmp($value, 'getdate') === 0 => 'CURRENT_TIMESTAMP',
            is_numeric($value) => $value,
            (bool) preg_match("/^N?'(.*)'$/s", $value, $text) => $this->quote($text[1]),
            default => throw new RuntimeException("No MySQL default for {$table}.{$column->name}: {$column->default_value}"),
        };
    }

    /**
     * @param  resource  $file
     * @param  list<object>  $columns
     * @return int rows written
     */
    private function writeRows($file, string $database, Connection $db, string $table, array $columns): int
    {
        $types = collect($columns)->pluck('type', 'name')->all();
        $header = "INSERT INTO `{$table}` (`".implode('`, `', array_keys($types)).'`) VALUES';

        $query = $db->table($table)->select(array_keys($types));
        $this->rowFilter($database, $db, $table, $query);

        $identity = collect($columns)->firstWhere('is_identity', 1)?->name;
        if ($identity !== null) {
            $query->orderBy($identity);
        }

        $count = 0;
        $batch = [];
        $batchBytes = 0;

        foreach ($query->cursor() as $row) {
            $row = $this->scrub($database, $table, (array) $row);
            $values = [];
            foreach ($types as $column => $type) {
                $values[] = $this->literal($row[$column], $type);
            }

            $line = '('.implode(', ', $values).')';
            $batch[] = $line;
            $batchBytes += strlen($line);
            $count++;

            // Small statements stay under the low max_allowed_packet of shared hosts.
            if (count($batch) >= self::ROWS_PER_INSERT || $batchBytes >= self::MAX_INSERT_BYTES) {
                fwrite($file, $header."\n".implode(",\n", $batch).";\n");
                $batch = [];
                $batchBytes = 0;
            }
        }

        if ($batch !== []) {
            fwrite($file, $header."\n".implode(",\n", $batch).";\n");
        }
        fwrite($file, "\n");

        return $count;
    }

    /**
     * Trims the two large tables down to a demo-sized set.
     */
    private function rowFilter(string $database, Connection $db, string $table, Builder $query): void
    {
        if ($database === 'ostdr' && $table === 'audits') {
            if ($firstKept = $this->firstKeptId($db, 'audits', (int) $this->option('audits'))) {
                $query->where('id', '>=', $firstKept);
            }

            // Legacy audited some user changes with the password hash in the old/new values.
            $query->where(fn ($audits) => $audits->whereNull('old_values')->orWhere('old_values', 'not like', '%password%'))
                ->where(fn ($audits) => $audits->whereNull('new_values')->orWhere('new_values', 'not like', '%password%'));
        }

        if ($database === 'catalogue' && $table === 'products') {
            if ($firstKept = $this->firstKeptId($db, 'products', (int) $this->option('products'))) {
                // Existing requests must still find their items, however old those products are.
                $requested = DB::connection('sqlsrv')->table('requested_items')
                    ->whereNotNull('stock_code')->distinct()->pluck('stock_code')->all();

                $query->where(fn ($products) => $products->where('id', '>=', $firstKept)->orWhereIn('code', $requested));
            }
        }
    }

    /**
     * The id at which the latest $limit rows start, or null to keep every row.
     */
    private function firstKeptId(Connection $db, string $table, int $limit): mixed
    {
        return $limit > 0
            ? $db->table($table)->orderByDesc('id')->offset($limit - 1)->limit(1)->value('id')
            : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function scrub(string $database, string $table, array $row): array
    {
        if ($database === 'ostdr' && $table === 'users') {
            // A remember token is a login credential, and nothing is lost by logging in again.
            $row['remember_token'] = null;

            if (filled($this->option('demo-password'))) {
                $row['password'] = $this->demoPasswordHash ??= Hash::make($this->option('demo-password'));
            }
        }

        return $row;
    }

    private function literal(mixed $value, string $type): string
    {
        return match (true) {
            $value === null => 'NULL',
            $type === 'bit' => (string) (int) (bool) $value,
            in_array($type, ['bigint', 'int', 'smallint', 'tinyint', 'decimal', 'numeric', 'money'], true) => (string) $value,
            // MySQL DATETIME and TIME hold whole seconds here; SQL Server returns fractions.
            in_array($type, ['datetime', 'datetime2', 'smalldatetime'], true) => $this->quote(substr((string) $value, 0, 19)),
            $type === 'time' => $this->quote(substr((string) $value, 0, 8)),
            default => $this->quote((string) $value),
        };
    }

    private function quote(string $value): string
    {
        return "'".strtr($value, ['\\' => '\\\\', "'" => "''", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z'])."'";
    }
}
