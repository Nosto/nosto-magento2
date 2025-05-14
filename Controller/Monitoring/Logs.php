<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use ZipArchive;

class Logs implements ActionInterface
{
    private const string LOG_LOCATION = BP . '/var/log/';

    private const string ARCHIVE_NAME = 'nosto-logs.zip';

    public function execute()
    {
        $fileNames = [];

        $logFiles = scandir(self::LOG_LOCATION);
        foreach ($logFiles as $logFile) {
            if (str_starts_with($logFile, 'nosto')) {
                $fileNames[] = $logFile;
            }
        }

        $this->compressAndDownloadLogFiles($fileNames);
    }

    private function compressAndDownloadLogFiles(array $files): void
    {
        $zip = new ZipArchive();
        if ($zip->open(self::ARCHIVE_NAME, ZipArchive::CREATE) !== true) {
            exit('Cannot open ' . self::ARCHIVE_NAME);
        }

        foreach ($files as $file) {
            $zip->addFile(self::LOG_LOCATION . $file, $file);
        }
        $zip->close();

        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename = " . (self::ARCHIVE_NAME) . "");
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile(self::ARCHIVE_NAME);
        exit;
    }
}
