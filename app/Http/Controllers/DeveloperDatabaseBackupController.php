<?php

namespace App\Http\Controllers;

use App\Services\DatabaseMaintenance;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeveloperDatabaseBackupController extends Controller
{
    public function __invoke(DatabaseMaintenance $maintenance): StreamedResponse
    {
        abort_unless(auth()->user()?->isDeveloper(), 403);

        $filename = 'pancor-mas-backup-'.now()->format('Ymd-His').'.sql';

        return response()->streamDownload(function () use ($maintenance): void {
            $handle = fopen('php://output', 'wb');
            $maintenance->writeBackup($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'application/sql; charset=UTF-8']);
    }
}
