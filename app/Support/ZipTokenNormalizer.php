<?php

namespace App\Support;

use Illuminate\Support\Str;

class ZipTokenNormalizer
{
    public static function normalizeWithToken(string $sourceZipPath, string $token, string $prefix = 'zip_req'): string
    {
        $tempExtractDir = storage_path('app/tmp/'.$prefix.'_'.Str::random(12));
        if (! is_dir($tempExtractDir)) {
            mkdir($tempExtractDir, 0777, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($sourceZipPath) !== true) {
            throw new \RuntimeException('ZIP tidak bisa dibuka.');
        }
        $zip->extractTo($tempExtractDir);
        $zip->close();

        $outputZipPath = storage_path('app/tmp/'.$prefix.'_out_'.Str::random(12).'.zip');
        $outputZip = new \ZipArchive();
        if ($outputZip->open($outputZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            self::deleteDir($tempExtractDir);
            throw new \RuntimeException('ZIP output tidak bisa dibuat.');
        }

        $usedNames = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempExtractDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $basename = $item->getBasename();
            $safeName = self::uniqueZipName($token.'_'.$basename, $usedNames);
            $outputZip->addFile($item->getPathname(), $safeName);
        }

        $outputZip->close();
        self::deleteDir($tempExtractDir);

        return $outputZipPath;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    private static function uniqueZipName(string $name, array &$usedNames): string
    {
        $candidate = $name;
        $counter = 2;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        while (in_array(strtolower($candidate), $usedNames, true)) {
            $candidate = $base.'_'.$counter.($ext ? '.'.$ext : '');
            $counter++;
        }

        $usedNames[] = strtolower($candidate);

        return $candidate;
    }

    private static function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

