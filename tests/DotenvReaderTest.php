<?php

namespace Jackiedo\DotenvEditor\Tests;

use Jackiedo\DotenvEditor\DotenvReader;
use Jackiedo\DotenvEditor\Exceptions\UnableReadFileException;
use Jackiedo\DotenvEditor\Workers\Parsers\ParserV3;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DotenvReaderTest extends TestCase
{
    private DotenvReader $reader;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->reader = new DotenvReader(new ParserV3());
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'dotenv');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    #[Test]
    public function readsFileContent(): void
    {
        file_put_contents($this->tmpFile, "APP_KEY=value\nDB_HOST=localhost");
        $this->reader->load($this->tmpFile);

        $this->assertSame("APP_KEY=value\nDB_HOST=localhost", $this->reader->content());
    }

    #[Test]
    public function throwsWhenFileNotReadable(): void
    {
        $this->expectException(UnableReadFileException::class);
        $this->reader->load('/nonexistent/file/.env');
        $this->reader->content();
    }

    #[Test]
    public function returnsEntriesWithoutParsedData(): void
    {
        file_put_contents($this->tmpFile, "APP_KEY=value\n# comment\n\nDB_HOST=localhost");
        $this->reader->load($this->tmpFile);

        $entries = $this->reader->entries(false);
        $this->assertCount(4, $entries);
        $this->assertSame('APP_KEY=value', $entries[0]['raw_data']);
        $this->assertArrayNotHasKey('parsed_data', $entries[0]);
    }

    #[Test]
    public function returnsEntriesWithParsedData(): void
    {
        file_put_contents($this->tmpFile, "APP_KEY=value\n# comment");
        $this->reader->load($this->tmpFile);

        $entries = $this->reader->entries(true);
        $this->assertCount(2, $entries);
        $this->assertArrayHasKey('parsed_data', $entries[0]);
        $this->assertSame('setter', $entries[0]['parsed_data']['type']);
        $this->assertSame('APP_KEY', $entries[0]['parsed_data']['key']);
        $this->assertSame('value', $entries[0]['parsed_data']['value']);
    }

    #[Test]
    public function returnsAllKeys(): void
    {
        file_put_contents($this->tmpFile, "APP_KEY=value\n# comment\nDB_HOST=localhost");
        $this->reader->load($this->tmpFile);

        $keys = $this->reader->keys();
        $this->assertCount(2, $keys);
        $this->assertArrayHasKey('APP_KEY', $keys);
        $this->assertArrayHasKey('DB_HOST', $keys);
        $this->assertSame('value', $keys['APP_KEY']['value']);
        $this->assertSame('localhost', $keys['DB_HOST']['value']);
    }

    #[Test]
    public function excludesCommentsAndEmptyLinesFromKeys(): void
    {
        file_put_contents($this->tmpFile, "# comment\n\nAPP_KEY=value");
        $this->reader->load($this->tmpFile);

        $keys = $this->reader->keys();
        $this->assertCount(1, $keys);
        $this->assertArrayHasKey('APP_KEY', $keys);
    }

    #[Test]
    public function includesExportInfoInKeys(): void
    {
        file_put_contents($this->tmpFile, 'export APP_KEY=value');
        $this->reader->load($this->tmpFile);

        $keys = $this->reader->keys();
        $this->assertTrue($keys['APP_KEY']['export']);
    }

    #[Test]
    public function includesCommentInKeyInfo(): void
    {
        file_put_contents($this->tmpFile, 'APP_KEY=value # my comment');
        $this->reader->load($this->tmpFile);

        $keys = $this->reader->keys();
        $this->assertSame('my comment', $keys['APP_KEY']['comment']);
    }

    #[Test]
    public function includesLineNumberInKeyInfo(): void
    {
        file_put_contents($this->tmpFile, "# header\nAPP_KEY=value");
        $this->reader->load($this->tmpFile);

        $keys = $this->reader->keys();
        $this->assertSame(2, $keys['APP_KEY']['line']);
    }
}
