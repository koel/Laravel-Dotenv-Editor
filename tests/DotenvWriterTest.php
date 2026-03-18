<?php

namespace Jackiedo\DotenvEditor\Tests;

use Jackiedo\DotenvEditor\DotenvWriter;
use Jackiedo\DotenvEditor\Exceptions\UnableWriteToFileException;
use Jackiedo\DotenvEditor\Workers\Formatters\Formatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DotenvWriterTest extends TestCase
{
    private DotenvWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new DotenvWriter(new Formatter());
    }

    #[Test]
    public function startsWithEmptyBuffer(): void
    {
        $this->writer->setBuffer([]);
        $this->assertSame([], $this->writer->getBuffer());
    }

    #[Test]
    public function setsAndGetsBuffer(): void
    {
        $buffer = [
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val', 'comment' => ''],
        ];
        $this->writer->setBuffer($buffer);
        $this->assertSame($buffer, $this->writer->getBuffer(true));
    }

    #[Test]
    public function appendsEmptyLine(): void
    {
        $this->writer->setBuffer([]);
        $this->writer->appendEmpty();
        $buffer = $this->writer->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('empty', $buffer[0]['type']);
    }

    #[Test]
    public function appendsComment(): void
    {
        $this->writer->setBuffer([]);
        $this->writer->appendComment('Test comment');
        $buffer = $this->writer->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('comment', $buffer[0]['type']);
        $this->assertSame('Test comment', $buffer[0]['comment']);
    }

    #[Test]
    public function appendsSetter(): void
    {
        $this->writer->setBuffer([]);
        $this->writer->appendSetter('APP_KEY', 'myvalue', 'a comment', false);
        $buffer = $this->writer->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('setter', $buffer[0]['type']);
        $this->assertSame('APP_KEY', $buffer[0]['key']);
        $this->assertSame('myvalue', $buffer[0]['value']);
        $this->assertSame('a comment', $buffer[0]['comment']);
        $this->assertFalse($buffer[0]['export']);
    }

    #[Test]
    public function appendsSetterWithExport(): void
    {
        $this->writer->setBuffer([]);
        $this->writer->appendSetter('APP_KEY', 'val', null, true);
        $buffer = $this->writer->getBuffer();
        $this->assertTrue($buffer[0]['export']);
    }

    #[Test]
    public function updatesSetterValue(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'old', 'comment' => ''],
        ]);
        $this->writer->updateSetter('APP_KEY', 'new', 'updated', false);
        $buffer = $this->writer->getBuffer();
        $this->assertSame('new', $buffer[0]['value']);
        $this->assertSame('updated', $buffer[0]['comment']);
    }

    #[Test]
    public function updatesOnlyMatchingSetter(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val1', 'comment' => ''],
            ['line' => 2, 'type' => 'setter', 'export' => false, 'key' => 'DB_HOST', 'value' => 'val2', 'comment' => ''],
        ]);
        $this->writer->updateSetter('DB_HOST', 'newval', '', false);
        $buffer = $this->writer->getBuffer();
        $this->assertSame('val1', $buffer[0]['value']);
        $this->assertSame('newval', $buffer[1]['value']);
    }

    #[Test]
    public function updatesSetterComment(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val', 'comment' => 'old'],
        ]);
        $this->writer->updateSetterComment('APP_KEY', 'new comment');
        $buffer = $this->writer->getBuffer();
        $this->assertSame('new comment', $buffer[0]['comment']);
    }

    #[Test]
    public function updatesSetterExport(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val', 'comment' => ''],
        ]);
        $this->writer->updateSetterExport('APP_KEY', true);
        $buffer = $this->writer->getBuffer();
        $this->assertTrue($buffer[0]['export']);
    }

    #[Test]
    public function deletesSetter(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val1', 'comment' => ''],
            ['line' => 2, 'type' => 'setter', 'export' => false, 'key' => 'DB_HOST', 'value' => 'val2', 'comment' => ''],
        ]);
        $this->writer->deleteSetter('APP_KEY');
        $buffer = $this->writer->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('DB_HOST', $buffer[0]['key']);
    }

    #[Test]
    public function doesNotDeleteNonSetterEntries(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'comment', 'export' => false, 'key' => '', 'value' => '', 'comment' => 'A comment'],
            ['line' => 2, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'val', 'comment' => ''],
        ]);
        $this->writer->deleteSetter('APP_KEY');
        $buffer = $this->writer->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('comment', $buffer[0]['type']);
    }

    #[Test]
    public function buildsTextContentFromBuffer(): void
    {
        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'value', 'comment' => ''],
            ['line' => 2, 'type' => 'empty', 'export' => false, 'key' => '', 'value' => '', 'comment' => ''],
            ['line' => 3, 'type' => 'comment', 'export' => false, 'key' => '', 'value' => '', 'comment' => 'Database'],
            ['line' => 4, 'type' => 'setter', 'export' => false, 'key' => 'DB_HOST', 'value' => 'localhost', 'comment' => ''],
        ]);

        $text = $this->writer->getBuffer(false);
        $expected = "APP_KEY=value" . PHP_EOL . "" . PHP_EOL . "# Database" . PHP_EOL . "DB_HOST=localhost" . PHP_EOL;
        $this->assertSame($expected, $text);
    }

    #[Test]
    public function savesToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'dotenv');

        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'APP_KEY', 'value' => 'value', 'comment' => ''],
            ['line' => 2, 'type' => 'setter', 'export' => true, 'key' => 'DB_HOST', 'value' => 'localhost', 'comment' => 'db host'],
        ]);

        $this->writer->saveTo($tmpFile);
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString('APP_KEY=value', $content);
        $this->assertStringContainsString('export DB_HOST=localhost # db host', $content);
    }

    #[Test]
    public function throwsWhenFileIsNotWritable(): void
    {
        $this->expectException(UnableWriteToFileException::class);
        $this->writer->setBuffer([]);
        $this->writer->saveTo('/proc/nonexistent/path/file');
    }

    #[Test]
    public function createsFileIfParentDirIsWritable(): void
    {
        $tmpDir = sys_get_temp_dir() . '/dotenv_test_' . uniqid();
        mkdir($tmpDir);
        $file = $tmpDir . '/.env';

        $this->writer->setBuffer([
            ['line' => 1, 'type' => 'setter', 'export' => false, 'key' => 'FOO', 'value' => 'bar', 'comment' => ''],
        ]);
        $this->writer->saveTo($file);

        $this->assertFileExists($file);
        $this->assertStringContainsString('FOO=bar', file_get_contents($file));

        unlink($file);
        rmdir($tmpDir);
    }
}
