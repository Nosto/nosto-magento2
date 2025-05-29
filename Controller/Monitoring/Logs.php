<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Controller\Monitoring;

use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Filesystem\Driver\File;
use SplFileInfo;
use ZipArchive;

class Logs implements ActionInterface
{
    private const ARCHIVE_NAME = 'nosto-logs.zip';

    /** @var FileFactory $fileFactory */
    private FileFactory $fileFactory;

    /** @var File $file */
    private File $fileDriver;

    /** @var DirectoryList $directoryList */
    private DirectoryList $directoryList;

    /**
     * Logs constructor
     *
     * @param FileFactory $fileFactory
     * @param File $fileDriver
     * @param DirectoryList $directoryList
     */
    public function __construct(
        FileFactory $fileFactory,
        File $fileDriver,
        DirectoryList $directoryList
    ) {
        $this->fileFactory = $fileFactory;
        $this->fileDriver = $fileDriver;
        $this->directoryList = $directoryList;
    }

    /**
     * Check if user have permission for log folder and download log files
     * @throws Exception
     */
    public function execute(): ResponseInterface
    {
        if (false === $this->checkPermissionsForLogsFolder()) {
            throw new StateException(__('Permission denied!.'));
        }

        $zipFilePath = $this->directoryList->getRoot() . '/var/log/' . self::ARCHIVE_NAME;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new StateException(__('Can not create ZIP file.'));
        }

        foreach ($this->getNostoLogFiles() as $file) {
            $fullPathName = new SplFileInfo($file);
            $filename = $fullPathName->getFilename();
            if ($this->fileDriver->isExists($file)) {
                $zip->addFile($file, $filename);
            }
        }
        $zip->close();

        // Serve ZIP file for download
        return $this->fileFactory->create(
            self::ARCHIVE_NAME,
            [
                'type'  => 'filename',
                'value' => $zipFilePath,
                'rm'    => true
            ],
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }

    /**
     * Get log files with nosto prefix
     *
     * @return array
     * @throws FileSystemException
     */
    private function getNostoLogFiles(): array
    {
        $fileNames = [];

        $logFiles = $this->fileDriver->readDirectory($this->directoryList->getRoot() . '/var/log/');
        foreach ($logFiles as $logFile) {
            $fullPathName = new SplFileInfo($logFile);
            $filename = $fullPathName->getFilename();
            if (str_starts_with($filename, 'nosto')) {
                $fileNames[] = $logFile;
            }
        }

        return $fileNames;
    }

    /**
     * Check permissions for logs folder
     *
     * @return bool
     * @throws FileSystemException
     */
    private function checkPermissionsForLogsFolder(): bool
    {
        if (!$this->fileDriver->isReadable($this->directoryList->getRoot() . '/var/log/')) {
            return false;
        }

        return true;
    }
}
