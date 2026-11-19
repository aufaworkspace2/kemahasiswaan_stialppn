<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class VerifyPortalBackup extends Command
{
    protected $signature = 'portal:backup-verify {archive}';

    protected $description = 'Verify every backup entry and restore files into an isolated temporary directory';

    public function handle(): int
    {
        $path = (string) $this->argument('archive');
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->error('Cannot read archive.');

            return self::FAILURE;
        }
        $work = storage_path('app/backups/verify-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($work, 0700);
        try {
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (empty($manifest['files']['database.sql'])) {
                throw new \RuntimeException('Missing SQL dump.');
            }
            foreach ($manifest['files'] as $name => $hash) {
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw new \RuntimeException('Unsafe archive path.');
                }
                $stream = $zip->getStream($name);
                if (! $stream) {
                    throw new \RuntimeException('Missing file.');
                }
                $target = $work.'/'.$name;
                File::ensureDirectoryExists(dirname($target), 0700);
                $output = fopen($target, 'wb');
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                chmod($target, 0600);
                if (! hash_equals($hash, hash_file('sha256', $target))) {
                    throw new \RuntimeException('Checksum mismatch.');
                }
            }
            $this->info('Verified and restored '.count($manifest['files']).' files in an isolated directory.');
            $this->warn('MySQL import into a separate staging database is still required for a full restore drill.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $zip->close();
            File::deleteDirectory($work);
        }
    }
}
