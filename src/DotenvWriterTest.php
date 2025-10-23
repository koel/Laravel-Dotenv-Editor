<?php

namespace Jackiedo\DotenvEditor;

use Faker\Factory;
use Jackiedo\DotenvEditor\Workers\Formatters\Formatter;
use PHPUnit\Framework\TestCase;

class DotenvWriterTest extends TestCase
{
    private $faker;

    public function test_getBuffer_should_return_lines_joined_via_os_line_separator_by_default()
    {
        $formatter = new Formatter();
        $instance = new DotenvWriter($formatter);
        $instance->setEndsWithLinebreak(false);

        $keys = $this->faker->words();
        $values = array_map(fn($key) => $this->faker->sentence(), $keys);
        $lines = array_combine($keys, $values);
        foreach ($lines as $key => $value) {
            $instance->appendSetter($key, $value);
        }

        $result = $instance->getBuffer(false);
        $separator = PHP_EOL;
        $result = explode($separator, $result);
        $expected = array_map(fn($key, $value) => $formatter->formatSetter($key, $value), array_keys($lines), array_values($lines));
        self::assertSame($expected, $result);
    }

    public function test_getBuffer_should_return_lines_joined_via_unix_line_separator()
    {
        $formatter = new Formatter();
        $instance = new DotenvWriter($formatter);
        $instance->setEOLMode('unix');
        $instance->setEndsWithLinebreak(false);

        $keys = $this->faker->words();
        $values = array_map(fn($key) => $this->faker->sentence(), $keys);
        $lines = array_combine($keys, $values);
        foreach ($lines as $key => $value) {
            $instance->appendSetter($key, $value);
        }

        $result = $instance->getBuffer(false);
        $resultInvalid = explode("\r\n", $result);
        $result = explode("\n", $result);
        $expected = array_map(fn($key, $value) => $formatter->formatSetter($key, $value), array_keys($lines), array_values($lines));
        self::assertSame($expected, $result);
        self::assertNotSame($expected, $resultInvalid);
    }

    public function test_getBuffer_should_return_lines_joined_via_windows_line_separator()
    {
        $formatter = new Formatter();
        $instance = new DotenvWriter($formatter);
        $instance->setEOLMode('windows');
        $instance->setEndsWithLinebreak(false);

        $keys = $this->faker->words();
        $values = array_map(fn($key) => $this->faker->sentence(), $keys);
        $lines = array_combine($keys, $values);
        foreach ($lines as $key => $value) {
            $instance->appendSetter($key, $value);
        }

        $result = $instance->getBuffer(false);
        $resultInvalid = explode("\n", $result);
        $result = explode("\r\n", $result);
        $expected = array_map(fn($key, $value) => $formatter->formatSetter($key, $value), array_keys($lines), array_values($lines));
        self::assertSame($expected, $result);
        self::assertNotSame($expected, $resultInvalid);
    }

    public function test_getBuffer_should_return_plus_one_lines_when_ends_with_linebreak()
    {
        $formatter = new Formatter();
        $instance = new DotenvWriter($formatter);
        $instance->setEndsWithLinebreak(true);

        $keys = $this->faker->words();
        $values = array_map(fn($key) => $this->faker->sentence(), $keys);
        $lines = array_combine($keys, $values);
        foreach ($lines as $key => $value) {
            $instance->appendSetter($key, $value);
        }

        $result = $instance->getBuffer(false);
        $separator = PHP_EOL;
        $result = explode($separator, $result);
        self::assertCount(count($lines) + 1, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Factory::create();
    }


}
