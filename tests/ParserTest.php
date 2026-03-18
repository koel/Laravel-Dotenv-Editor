<?php

namespace Jackiedo\DotenvEditor\Tests;

use Jackiedo\DotenvEditor\Exceptions\InvalidValueException;
use Jackiedo\DotenvEditor\Workers\Parsers\ParserV3;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private ParserV3 $parser;

    protected function setUp(): void
    {
        $this->parser = new ParserV3();
    }

    #[Test]
    public function parsesEmptyLine(): void
    {
        $result = $this->parser->parseEntry('');
        $this->assertSame('empty', $result['type']);
    }

    #[Test]
    public function parsesWhitespaceOnlyLine(): void
    {
        $result = $this->parser->parseEntry('   ');
        $this->assertSame('empty', $result['type']);
    }

    #[Test]
    public function parsesCommentLine(): void
    {
        $result = $this->parser->parseEntry('# This is a comment');
        $this->assertSame('comment', $result['type']);
        $this->assertSame('This is a comment', $result['comment']);
    }

    #[Test]
    public function parsesCommentWithLeadingSpaces(): void
    {
        $result = $this->parser->parseEntry('  # Indented comment');
        $this->assertSame('comment', $result['type']);
        $this->assertSame('Indented comment', $result['comment']);
    }

    #[Test]
    public function parsesSimpleSetter(): void
    {
        $result = $this->parser->parseEntry('APP_KEY=value');
        $this->assertSame('setter', $result['type']);
        $this->assertSame('APP_KEY', $result['key']);
        $this->assertSame('value', $result['value']);
        $this->assertFalse($result['export']);
        $this->assertSame('', $result['comment']);
    }

    #[Test]
    public function parsesSetterWithEmptyValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY=');
        $this->assertSame('setter', $result['type']);
        $this->assertSame('APP_KEY', $result['key']);
        $this->assertSame('', $result['value']);
    }

    #[Test]
    public function parsesDoubleQuotedValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="hello world"');
        $this->assertSame('hello world', $result['value']);
    }

    #[Test]
    public function parsesSingleQuotedValue(): void
    {
        $result = $this->parser->parseEntry("APP_KEY='hello world'");
        $this->assertSame('hello world', $result['value']);
    }

    #[Test]
    public function parsesSetterWithInlineComment(): void
    {
        $result = $this->parser->parseEntry('APP_KEY=value # my comment');
        $this->assertSame('value', $result['value']);
        $this->assertSame('my comment', $result['comment']);
    }

    #[Test]
    public function parsesQuotedValueWithInlineComment(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="value" # my comment');
        $this->assertSame('value', $result['value']);
        $this->assertSame('my comment', $result['comment']);
    }

    #[Test]
    public function parsesExportSetter(): void
    {
        $result = $this->parser->parseEntry('export APP_KEY=value');
        $this->assertSame('setter', $result['type']);
        $this->assertTrue($result['export']);
        $this->assertSame('APP_KEY', $result['key']);
        $this->assertSame('value', $result['value']);
    }

    #[Test]
    public function parsesEscapedDoubleQuoteInValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="val\"ue"');
        $this->assertSame('val"ue', $result['value']);
    }

    #[Test]
    public function parsesEscapedBackslashInValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="val\\\\ue"');
        $this->assertSame('val\\ue', $result['value']);
    }

    #[Test]
    public function parsesEscapedNewlineInDoubleQuotedValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="line1\\nline2"');
        $this->assertSame("line1\nline2", $result['value']);
    }

    #[Test]
    public function parsesValueWithDollarSign(): void
    {
        $result = $this->parser->parseEntry('PRICE=100$');
        $this->assertSame('100$', $result['value']);
    }

    #[Test]
    public function returnsUnknownForInvalidEntry(): void
    {
        $result = $this->parser->parseEntry('not a valid entry without equals');
        $this->assertSame('unknown', $result['type']);
    }

    #[Test]
    public function throwsOnMissingClosingQuote(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->parser->parseEntry('APP_KEY="unclosed');
    }

    #[Test]
    public function throwsOnMissingClosingSingleQuote(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->parser->parseEntry("APP_KEY='unclosed");
    }

    #[Test]
    public function parsesFileIntoEntries(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'dotenv');
        file_put_contents($tmpFile, "APP_KEY=value\nDB_HOST=localhost\n# comment\n\nAPP_DEBUG=true");

        $entries = $this->parser->parseFile($tmpFile);
        unlink($tmpFile);

        $this->assertCount(5, $entries);
        $this->assertSame(1, $entries[0]['line']);
        $this->assertSame('APP_KEY=value', $entries[0]['raw_data']);
        $this->assertSame('DB_HOST=localhost', $entries[1]['raw_data']);
        $this->assertSame('# comment', $entries[2]['raw_data']);
        $this->assertSame('', $entries[3]['raw_data']);
        $this->assertSame('APP_DEBUG=true', $entries[4]['raw_data']);
    }

    #[Test]
    public function handlesValueWithEqualsSign(): void
    {
        $result = $this->parser->parseEntry('APP_KEY=base64:abc=def==');
        $this->assertSame('APP_KEY', $result['key']);
        $this->assertSame('base64:abc=def==', $result['value']);
    }

    #[Test]
    public function parsesHashInsideQuotedValueAsPartOfValue(): void
    {
        $result = $this->parser->parseEntry('APP_KEY="val#ue"');
        $this->assertSame('val#ue', $result['value']);
        $this->assertSame('', $result['comment']);
    }
}
