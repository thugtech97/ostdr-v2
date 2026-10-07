<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Stops and starts the app the legacy way (artisan down / up, signing everyone out),
 * with a bypass secret so whoever stopped it can still get back in.
 */
class MaintenanceMode
{
    /**
     * @return string the bypass secret; visiting /{secret} sets the bypass cookie
     */
    public function down(?string $keepSession = null): string
    {
        $secret = config('app.maintenance_secret') ?: Str::random(40);

        Artisan::call('down', ['--secret' => $secret]);

        $this->signEveryoneOut($keepSession);

        return $secret;
    }

    public function up(): void
    {
        Artisan::call('up');
    }

    public function isDown(): bool
    {
        return app()->isDownForMaintenance();
    }

    private function signEveryoneOut(?string $keepSession): void
    {
        match (config('session.driver')) {
            'database' => DB::table(config('session.table', 'sessions'))
                ->when($keepSession, fn ($query) => $query->where('id', '<>', $keepSession))
                ->delete(),
            'file' => collect(File::files(config('session.files')))
                ->reject(fn ($file) => $file->getFilename() === $keepSession)
                ->each(fn ($file) => File::delete($file->getPathname())),
            default => null,
        };
    }
}
