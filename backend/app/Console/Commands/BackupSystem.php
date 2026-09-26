<?php

namespace App\Console\Commands;

use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Encrypted backup of the database and private documents.
 *
 *   php artisan nis:backup --generate-key   print a new BACKUP_KEY
 *   php artisan nis:backup                  create a backup
 *   php artisan nis:backup --verify=FILE    decrypt and check a backup (restore drill)
 *
 * Archives are AES-256-GCM encrypted with BACKUP_KEY. Documents on S3 are
 * protected by the bucket's own versioning/replication; only the local
 * private disk is included here.
 */
class BackupSystem extends Command
{
    protected $signature = 'nis:backup {--generate-key} {--verify= : Path of a backup to check}';

    protected $description = 'Create (or verify) an encrypted backup of the database and documents';

    private const MAGIC = 'NISBK1';

    public function handle(): int
    {
        if ($this->option('generate-key')) {
            $this->line('BACKUP_KEY=base64:'.base64_encode(random_bytes(32)));
            $this->warn('Put this in backend/.env and keep a copy in a safe place: backups cannot be restored without it.');

            return self::SUCCESS;
        }

        $key = $this->key();
        if ($verify = $this->option('verify')) {
            return $this->verify((string) $verify, $key);
        }

        $dir = config('nis.backup.path');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        $stamp = now()->format('Ymd-His');
        $work = sys_get_temp_dir().DIRECTORY_SEPARATOR."nis-backup-{$stamp}";
        @mkdir($work, 0700, true);

        try {
            // 1. Database dump (custom format, restorable with pg_restore).
            $db = config('database.connections.pgsql');
            $dump = $work.DIRECTORY_SEPARATOR.'database.dump';
            $result = Process::env(['PGPASSWORD' => (string) $db['password']])->timeout(3600)->run([
                config('nis.backup.pg_dump'), '--format=custom', '--no-owner',
                '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--file='.$dump, $db['database'],
            ]);
            if (! $result->successful()) {
                throw new RuntimeException('pg_dump failed: '.trim($result->errorOutput()));
            }

            // 2. Zip the dump and the private documents.
            $zipPath = $work.DIRECTORY_SEPARATOR.'backup.zip';
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($dump, 'database.dump');
            $zip->addFromString('manifest.json', json_encode([
                'created_at' => now()->toIso8601String(), 'app_url' => config('app.url'), 'database' => $db['database'],
            ], JSON_PRETTY_PRINT));
            $docs = storage_path('app/private');
            if (is_dir($docs)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docs, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    $zip->addFile($file->getPathname(), 'documents/'.ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($docs))), '/'));
                }
            }
            $zip->close();

            // 3. Encrypt.
            $target = $dir.DIRECTORY_SEPARATOR."nis-rcis-{$stamp}.nisbak";
            $iv = random_bytes(12);
            $cipher = openssl_encrypt((string) file_get_contents($zipPath), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            file_put_contents($target, self::MAGIC.$iv.$tag.$cipher);
            @chmod($target, 0600);
        } finally {
            foreach (glob($work.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($work);
        }

        // 4. Keep the most recent N backups.
        $all = glob($dir.DIRECTORY_SEPARATOR.'nis-rcis-*.nisbak') ?: [];
        rsort($all);
        foreach (array_slice($all, max(1, config('nis.backup.keep'))) as $old) {
            @unlink($old);
        }

        $size = round(filesize($target) / 1048576, 1);
        Audit::log('BACKUP_CREATED', 'Encrypted backup created', context: ['file' => basename($target), 'size_mb' => $size], actorLabel: 'SYSTEM');
        $this->info("Backup written: {$target} ({$size} MB)");

        return self::SUCCESS;
    }

    private function verify(string $path, string $key): int
    {
        $raw = @file_get_contents($path);
        if ($raw === false || ! str_starts_with($raw, self::MAGIC)) {
            $this->error('Not an NIS-RCIS backup file.');

            return self::FAILURE;
        }
        $iv = substr($raw, 6, 12);
        $tag = substr($raw, 18, 16);
        $plain = openssl_decrypt(substr($raw, 34), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            $this->error('Decryption failed: wrong BACKUP_KEY or the file is damaged.');

            return self::FAILURE;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nisv');
        file_put_contents($tmp, $plain);
        $zip = new ZipArchive;
        $ok = $zip->open($tmp) === true && $zip->locateName('database.dump') !== false;
        $dump = $ok ? (string) $zip->getFromName('database.dump') : '';
        $documents = 0;
        for ($i = 0; $ok && $i < $zip->numFiles; $i++) {
            $documents += str_starts_with((string) $zip->getNameIndex($i), 'documents/') ? 1 : 0;
        }
        $zip->close();
        @unlink($tmp);

        if (! $ok || ! str_starts_with($dump, 'PGDMP')) {
            $this->error('The backup opened but does not contain a valid database dump.');

            return self::FAILURE;
        }

        $this->info('Backup OK: database dump '.round(strlen($dump) / 1024).' KB, '.$documents.' document file(s).');
        $this->line('To restore: decrypt with this command\'s key, then pg_restore --clean --dbname=... database.dump and copy documents/ to storage/app/private.');
        Audit::log('BACKUP_VERIFIED', 'Backup restore check passed', context: ['file' => basename($path)], actorLabel: 'SYSTEM');

        return self::SUCCESS;
    }

    private function key(): string
    {
        $key = (string) config('nis.backup.key');
        if ($key === '') {
            throw new RuntimeException('BACKUP_KEY is not set. Run: php artisan nis:backup --generate-key');
        }
        $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($bytes === false || strlen($bytes) !== 32) {
            throw new RuntimeException('BACKUP_KEY must be 32 bytes (use --generate-key).');
        }

        return $bytes;
    }
}
