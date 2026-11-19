<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupPortal extends Command
{
    protected $signature = 'portal:backup';

    protected $description = 'Back up MySQL and private/public portal files to a protected local archive';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('This command requires MySQL.');

            return self::FAILURE;
        }

        return Cache::lock('portal:backup', 600)->block(2, function (): int {
            $directory = storage_path('app/backups');
            File::ensureDirectoryExists($directory, 0700);
            chmod($directory, 0700);
            $work = $directory.'/work-'.bin2hex(random_bytes(8));
            File::makeDirectory($work, 0700);
            $path = $directory.'/portal-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';
            try {
                $config = config('database.connections.mysql');
                $escape = fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
                $optionFile = $work.'/client.cnf';
                file_put_contents($optionFile, "[client]\nuser=".$escape($config['username'])."\npassword=".$escape($config['password'])."\nhost=".$escape($config['host'])."\nport=".(int) $config['port']."\n");
                chmod($optionFile, 0600);
                $process = new Process(['mysqldump', '--defaults-extra-file='.$optionFile, '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--hex-blob', '--result-file='.$work.'/database.sql', $config['database']]);
                $process->setTimeout(300);
                $process->mustRun();
                touch($path);
                chmod($path, 0600);
                $zip = new ZipArchive;
                if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Cannot create archive.');
                } chmod($path, 0600);
                $manifest = ['created_at' => now()->toIso8601String(), 'database' => $config['database'], 'files' => []];
                $zip->addFile($work.'/database.sql', 'database.sql');
                $manifest['files']['database.sql'] = hash_file('sha256', $work.'/database.sql');
                foreach (['local' => 'private', 'public' => 'public'] as $disk => $prefix) {
                    $diskRoot = Storage::disk($disk)->path('');
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($diskRoot, \FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if (! $file->isFile() || $file->isLink()) {
                            continue;
                        } $relative = substr($file->getPathname(), strlen(rtrim($diskRoot, '/')) + 1);
                        if (str_starts_with($relative, '.')) {
                            continue;
                        } $name = $prefix.'/'.$relative;
                        $zip->addFile($file->getPathname(), $name);
                        $manifest['files'][$name] = hash_file('sha256', $file->getPathname());
                    }
                }
                $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $zip->close();
                $this->info('Backup created: '.$path);
                $this->line('Keep a separately protected copy of APP_KEY; it is intentionally excluded from this archive.');

                return self::SUCCESS;
            } catch (\Throwable $exception) {
                File::delete($path);
                report($exception);
                $this->error('Backup failed. Check server tools and permissions.');

                return self::FAILURE;
            } finally {
                File::deleteDirectory($work);
            }
        });
    }
}
