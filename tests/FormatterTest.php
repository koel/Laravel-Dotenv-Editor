<?php

namespace Jackiedo\DotenvEditor\Tests;

use Jackiedo\DotenvEditor\Exceptions\InvalidKeyException;
use Jackiedo\DotenvEditor\Workers\Formatters\Formatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    private Formatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new Formatter();
    }

    #[Test]
    public function formatsASimpleKey(): void
    {
        $this->assertSame('APP_KEY', $this->formatter->formatKey('APP_KEY'));
    }

    #[Test]
    public function formatsAKeyWithExportPrefix(): void
    {
        $this->assertSame('export APP_KEY', $this->formatter->formatKey('APP_KEY', true));
    }

    #[Test]
    public function stripsExistingExportPrefixFromKey(): void
    {
        $this->assertSame('APP_KEY', $this->formatter->formatKey('export APP_KEY', false));
    }

    #[Test]
    public function stripsQuotesFromKey(): void
    {
        $this->assertSame('APP_KEY', $this->formatter->formatKey('"APP_KEY"'));
        $this->assertSame('APP_KEY', $this->formatter->formatKey("'APP_KEY'"));
    }

    #[Test]
    public function throwsOnInvalidKey(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->formatter->formatKey('INVALID KEY WITH SPACES');
    }

    #[Test]
    public function allowsDotsAndUnderscoresInKeys(): void
    {
        $this->assertSame('APP.KEY_NAME', $this->formatter->formatKey('APP.KEY_NAME'));
    }

    #[Test]
    public function formatsAComment(): void
    {
        $this->assertSame('# This is a comment', $this->formatter->formatComment('This is a comment'));
    }

    #[Test]
    public function stripsLeadingHashFromComment(): void
    {
        $this->assertSame('# Already a comment', $this->formatter->formatComment('# Already a comment'));
    }

    #[Test]
    public function returnsEmptyStringForEmptyComment(): void
    {
        $this->assertSame('', $this->formatter->formatComment(''));
        $this->assertSame('', $this->formatter->formatComment(null));
    }

    #[Test]
    public function stripsNewlinesFromComment(): void
    {
        $this->assertSame('# line1 line2', $this->formatter->formatComment("line1\nline2"));
    }

    #[Test]
    public function formatsASimpleSetter(): void
    {
        $this->assertSame('APP_KEY=value', $this->formatter->formatSetter('APP_KEY', 'value'));
    }

    #[Test]
    public function formatsASetterWithEmptyValue(): void
    {
        $this->assertSame('APP_KEY=', $this->formatter->formatSetter('APP_KEY', ''));
    }

    #[Test]
    public function formatsASetterWithNullValue(): void
    {
        $this->assertSame('APP_KEY=', $this->formatter->formatSetter('APP_KEY', null));
    }

    #[Test]
    public function quotesValuesContainingSpaces(): void
    {
        $this->assertSame('APP_KEY="hello world"', $this->formatter->formatSetter('APP_KEY', 'hello world'));
    }

    #[Test]
    public function quotesValuesContainingHash(): void
    {
        $this->assertSame('APP_KEY="val#ue"', $this->formatter->formatSetter('APP_KEY', 'val#ue'));
    }

    #[Test]
    public function quotesValuesContainingDoubleQuotes(): void
    {
        $this->assertSame('APP_KEY="val\"ue"', $this->formatter->formatSetter('APP_KEY', 'val"ue'));
    }

    #[Test]
    public function escapesBackslashesInValues(): void
    {
        $this->assertSame('APP_KEY="val\\\\ue"', $this->formatter->formatSetter('APP_KEY', 'val\\ue'));
    }

    #[Test]
    public function formatsASetterWithComment(): void
    {
        $this->assertSame('APP_KEY=value # This is a comment', $this->formatter->formatSetter(
            'APP_KEY',
            'value',
            'This is a comment',
        ));
    }

    #[Test]
    public function quotesEmptyValueWhenCommentPresent(): void
    {
        $this->assertSame('APP_KEY="" # A comment', $this->formatter->formatSetter('APP_KEY', '', 'A comment'));
    }

    #[Test]
    public function formatsASetterWithExport(): void
    {
        $this->assertSame('export APP_KEY=value', $this->formatter->formatSetter('APP_KEY', 'value', null, true));
    }

    #[Test]
    public function formatsASetterWithVariableInterpolation(): void
    {
        $this->assertSame('APP_KEY="${DB_HOST}"', $this->formatter->formatSetter('APP_KEY', '${DB_HOST}'));
    }
}
