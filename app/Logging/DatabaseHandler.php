<?php

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

/**
 * Writes log records to the legacy `log` table (the danielme85/laravel-log-to-db layout)
 * that the Application Error Logs report reads.
 */
class DatabaseHandler extends AbstractProcessingHandler
{
    private bool $writing = false;

    protected function write(LogRecord $record): void
    {
        // A failing insert logs an error itself; never recurse into this handler.
        if ($this->writing) {
            return;
        }

        $this->writing = true;

        try {
            $user = Auth::hasUser() ? Auth::user() : null;

            DB::table('log')->insert([
                'message' => mb_substr($record->message, 0, 4000),
                'channel' => $record->channel,
                'level' => $record->level->value,
                'level_name' => $record->level->getName(),
                'unix_time' => $record->datetime->getTimestamp(),
                'datetime' => $record->datetime->format('Y-m-d H:i:s'),
                'context' => mb_substr(json_encode($this->context($record->context), JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '', 0, 20000),
                'extra' => json_encode($record->extra, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
                'userid' => $user?->getAuthIdentifier(),
                'username' => $user?->username,
            ]);
        } catch (Throwable) {
            // The file log still has the record.
        } finally {
            $this->writing = false;
        }
    }

    /**
     * Exceptions do not serialise; keep what the report needs.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function context(array $context): array
    {
        if (($context['exception'] ?? null) instanceof Throwable) {
            $e = $context['exception'];
            $context['exception'] = [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        return $context;
    }
}
