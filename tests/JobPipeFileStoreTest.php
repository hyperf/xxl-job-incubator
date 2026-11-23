<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\JobPipeFileStore;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\JobPipeFileStore
 */
class JobPipeFileStoreTest extends TestCase
{
    public function testCleanupRemovesOnlyExpiredPayloadsAndLegacyFiles(): void
    {
        $config = new Config();
        $config->setAppName('payload-cleanup-test');
        $config->setLogFileDir('/tmp/payload-cleanup-' . uniqid('', true));
        $store = new JobPipeFileStore($config);
        $legacy = sys_get_temp_dir() . '/xxl_job_pipe_legacy_' . uniqid('', true);
        try {
            $expired = $store->putPayload('expired');
            $fresh = $store->putPayload('fresh');
            file_put_contents($legacy, 'legacy');
            touch($expired, time() - 400);
            touch($legacy, time() - 400);

            $store->cleanup(300);

            self::assertFileDoesNotExist($expired);
            self::assertFileDoesNotExist($legacy);
            self::assertFileExists($fresh);
        } finally {
            if (is_file($legacy)) {
                unlink($legacy);
            }
            $store->cleanup(-1);
            if (is_dir($store->getDirectory())) {
                rmdir($store->getDirectory());
            }
        }
    }
}
