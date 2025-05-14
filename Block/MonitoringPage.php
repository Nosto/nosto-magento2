<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use ZipArchive;

class MonitoringPage extends Template
{
    private const string LOG_LOCATION = BP . '/var/log/';

    private const string ARCHIVE_NAME = 'nosto-logs.zip';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getLogoutFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logout', ['_secure' => true]);
    }

    public function getDownloadLogFilesFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logs', ['_secure' => true]);

//        $fileNames = [];
//
//        $logFiles = scandir(self::LOG_LOCATION);
//        foreach ($logFiles as $logFile) {
//            if (str_starts_with($logFile, 'nosto')) {
//                $fileNames[] = $logFile;
//            }
//        }
//
//        $zipName = 'nosto-logs.zip';
//
//        $this->compressAndDownloadLogFiles($fileNames, $zipName);
    }

    private function compressAndDownloadLogFiles(array $files, string $zipFileName): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE) !== TRUE) {
            exit('Cannot open ' . $zipFileName);
        }

        foreach ($files as $file) {
            $zip->addFile(self::LOG_LOCATION . $file, $file);
        }
        $zip->close();

        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename = $zipFileName");
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zipFileName");
        exit;
    }
}
