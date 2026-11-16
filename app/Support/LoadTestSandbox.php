<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class LoadTestSandbox
{
    public static function active(): bool
    {
        if (! app()->environment('loadtest')) {
            return false;
        }

        $db = DB::connection();
        $fixture = database_path('loadtest.sqlite');

        // Check the driver and a real, independent file; false === false
        // would otherwise accept two unresolved paths.
        return $db->getDriverName() === 'sqlite'
            && is_file($fixture)
            && ! is_link($fixture)
            && (stat($fixture)['nlink'] ?? 0) === 1
            && realpath($db->getDatabaseName()) === realpath($fixture);
    }
}
