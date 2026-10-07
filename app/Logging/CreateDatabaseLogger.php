<?php

namespace App\Logging;

use Monolog\Level;
use Monolog\Logger;

class CreateDatabaseLogger
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        return new Logger('database', [new DatabaseHandler(Level::fromName($config['level'] ?? 'error'))]);
    }
}
