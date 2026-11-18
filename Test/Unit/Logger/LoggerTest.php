<?php

/**
 * Copyright (c) 2026, Nosto Solutions Ltd
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
 * @copyright 2026 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

declare(strict_types=1);

namespace Nosto\Tagging\Test\Unit\Logger;

use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Nosto\Tagging\Logger\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * @covers \Nosto\Tagging\Logger\Logger
 */
#[CoversClass(Logger::class)]
class LoggerTest extends TestCase
{
    public function testForwardsAllLevelsToTheConfiguredChannelAndHandlers(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new MonologLogger('nosto', [$handler]));
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        foreach ($levels as $level) {
            $logger->$level('message', ['key' => 'value']);
        }

        $records = $handler->getRecords();
        self::assertCount(count($levels), $records);
        foreach ($records as $index => $record) {
            self::assertSame('nosto', $record['channel']);
            self::assertSame(strtoupper($levels[$index]), $record['level_name']);
            self::assertSame('message', $record['message']);
            self::assertSame(['key' => 'value'], $record['context']);
        }
    }

    public function testSourceMethodsPreserveContextAndRecordTheSourceClass(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new MonologLogger('nosto', [$handler]));
        $source = new stdClass();

        $logger->debugWithSource('debug message', ['storeId' => 3], $source);
        $logger->infoWithSource('info message', ['storeId' => 5], $source);

        $records = $handler->getRecords();
        self::assertCount(2, $records);
        self::assertSame('DEBUG', $records[0]['level_name']);
        self::assertSame('debug message', $records[0]['message']);
        self::assertSame(['storeId' => 3, 'sourceClass' => stdClass::class], $records[0]['context']);
        self::assertSame('INFO', $records[1]['level_name']);
        self::assertSame('info message', $records[1]['message']);
        self::assertSame(['storeId' => 5, 'sourceClass' => stdClass::class], $records[1]['context']);
    }

    public function testExceptionIsLoggedAsItsFullStringAtErrorLevel(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new MonologLogger('nosto', [$handler]));
        $exception = new RuntimeException('failure');

        $logger->exception($exception);

        $records = $handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('ERROR', $records[0]['level_name']);
        self::assertSame((string)$exception, $records[0]['message']);
    }
}
