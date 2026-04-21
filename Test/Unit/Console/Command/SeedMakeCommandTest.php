<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Console\Command\SeedMakeCommand;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedMakeCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/seeder-make-' . uniqid();
        mkdir($this->workspace, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspace)) {
            $this->removeRecursive($this->workspace);
        }
    }

    public function test_writes_php_seeder_file_with_all_flags(): void
    {
        $command = $this->makeCommand(['order', 'customer']);

        $tester = new CommandTester($command);
        $tester->setInputs([]); // no interactive input
        $exit = $tester->execute(
            [
                '--type' => 'order',
                '--count' => '25',
                '--format' => 'php',
                '--locale' => 'en_US',
                '--name' => 'QuickOrderSeeder',
            ],
            ['interactive' => false],
        );

        $this->assertSame(Command::SUCCESS, $exit);

        $file = $this->workspace . '/dev/seeders/QuickOrderSeeder.php';
        $this->assertFileExists($file);

        $config = require $file;
        $this->assertSame('order', $config['type']);
        $this->assertSame(25, $config['count']);
        $this->assertSame('en_US', $config['locale']);
    }

    /** @param string[] $knownTypes */
    private function makeCommand(array $knownTypes): SeedMakeCommand
    {
        $generators = array_fill_keys(
            $knownTypes,
            $this->createStub(\RunAsRoot\Seeder\Api\DataGeneratorInterface::class),
        );
        $pool = new DataGeneratorPool($generators);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->workspace);

        return new SeedMakeCommand(new SeederFileBuilder(), $pool, $directoryList);
    }

    private function removeRecursive(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
