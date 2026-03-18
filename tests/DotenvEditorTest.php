<?php

namespace Jackiedo\DotenvEditor\Tests;

use Jackiedo\DotenvEditor\DotenvEditor;
use Jackiedo\DotenvEditor\Exceptions\KeyNotFoundException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DotenvEditorTest extends TestCase
{
    private DotenvEditor $editor;
    private string $envFile;
    private string $tmpDir;

    protected function getPackageProviders($app): array
    {
        return [\Jackiedo\DotenvEditor\DotenvEditorServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/dotenv_editor_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . '/backups', 0777, true);

        $this->envFile = $this->tmpDir . '/.env';
        file_put_contents(
            $this->envFile,
            "APP_NAME=Laravel\nAPP_ENV=local\n# Database\nDB_HOST=127.0.0.1\nDB_PORT=3306\n",
        );

        $this->editor = $this->app->make('dotenv-editor');
        $this->editor->autoBackup(false);
        $this->editor->load($this->envFile);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tmpDir);
        parent::tearDown();
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->cleanDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    #[Test]
    public function readsRawContent(): void
    {
        $content = $this->editor->getContent();
        $this->assertStringContainsString('APP_NAME=Laravel', $content);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $content);
    }

    #[Test]
    public function getsAllKeys(): void
    {
        $keys = $this->editor->getKeys();
        $this->assertArrayHasKey('APP_NAME', $keys);
        $this->assertArrayHasKey('APP_ENV', $keys);
        $this->assertArrayHasKey('DB_HOST', $keys);
        $this->assertArrayHasKey('DB_PORT', $keys);
        $this->assertCount(4, $keys);
    }

    #[Test]
    public function getsSpecificKeys(): void
    {
        $keys = $this->editor->getKeys(['APP_NAME', 'DB_HOST']);
        $this->assertCount(2, $keys);
        $this->assertArrayHasKey('APP_NAME', $keys);
        $this->assertArrayHasKey('DB_HOST', $keys);
    }

    #[Test]
    public function getsSingleKeyInfo(): void
    {
        $info = $this->editor->getKey('APP_NAME');
        $this->assertSame('Laravel', $info['value']);
        $this->assertFalse($info['export']);
    }

    #[Test]
    public function getsValueForKey(): void
    {
        $this->assertSame('Laravel', $this->editor->getValue('APP_NAME'));
        $this->assertSame('127.0.0.1', $this->editor->getValue('DB_HOST'));
    }

    #[Test]
    public function throwsWhenKeyNotFound(): void
    {
        $this->expectException(KeyNotFoundException::class);
        $this->editor->getKey('NONEXISTENT');
    }

    #[Test]
    public function checksKeyExistence(): void
    {
        $this->assertTrue($this->editor->keyExists('APP_NAME'));
        $this->assertFalse($this->editor->keyExists('NONEXISTENT'));
    }

    #[Test]
    public function setsANewKey(): void
    {
        $this->editor->setKey('NEW_KEY', 'new_value');
        $this->assertTrue($this->editor->hasChanged());

        $buffer = $this->editor->getBuffer();
        $keys = array_column(array_filter($buffer, fn($e) => $e['type'] === 'setter'), 'value', 'key');
        $this->assertSame('new_value', $keys['NEW_KEY']);
    }

    #[Test]
    public function updatesExistingKey(): void
    {
        $this->editor->setKey('APP_NAME', 'NewApp');
        $buffer = $this->editor->getBuffer();
        $keys = array_column(array_filter($buffer, fn($e) => $e['type'] === 'setter'), 'value', 'key');
        $this->assertSame('NewApp', $keys['APP_NAME']);
    }

    #[Test]
    public function setsMultipleKeys(): void
    {
        $this->editor->setKeys([
            'APP_NAME' => 'Updated',
            'NEW_VAR' => 'hello',
        ]);

        $buffer = $this->editor->getBuffer();
        $keys = array_column(array_filter($buffer, fn($e) => $e['type'] === 'setter'), 'value', 'key');
        $this->assertSame('Updated', $keys['APP_NAME']);
        $this->assertSame('hello', $keys['NEW_VAR']);
    }

    #[Test]
    public function setsKeyWithComment(): void
    {
        $this->editor->setKey('NEW_KEY', 'val', 'my comment');
        $buffer = $this->editor->getBuffer();
        $entry = array_values(array_filter($buffer, fn($e) => ($e['key'] ?? '') === 'NEW_KEY'));
        $this->assertSame('my comment', $entry[0]['comment']);
    }

    #[Test]
    public function deletesAKey(): void
    {
        $this->editor->deleteKey('DB_PORT');
        $buffer = $this->editor->getBuffer();
        $keys = array_column(array_filter($buffer, fn($e) => $e['type'] === 'setter'), 'value', 'key');
        $this->assertArrayNotHasKey('DB_PORT', $keys);
        $this->assertTrue($this->editor->hasChanged());
    }

    #[Test]
    public function deletesMultipleKeys(): void
    {
        $this->editor->deleteKeys(['DB_HOST', 'DB_PORT']);
        $buffer = $this->editor->getBuffer();
        $keys = array_column(array_filter($buffer, fn($e) => $e['type'] === 'setter'), 'value', 'key');
        $this->assertArrayNotHasKey('DB_HOST', $keys);
        $this->assertArrayNotHasKey('DB_PORT', $keys);
    }

    #[Test]
    public function addsEmptyLine(): void
    {
        $countBefore = count($this->editor->getBuffer());
        $this->editor->addEmpty();
        $this->assertCount($countBefore + 1, $this->editor->getBuffer());
        $this->assertTrue($this->editor->hasChanged());
    }

    #[Test]
    public function addsCommentLine(): void
    {
        $this->editor->addComment('Section header');
        $buffer = $this->editor->getBuffer();
        $last = end($buffer);
        $this->assertSame('comment', $last['type']);
        $this->assertSame('Section header', $last['comment']);
    }

    #[Test]
    public function savesChangesToFile(): void
    {
        $this->editor->setKey('NEW_KEY', 'saved_value');
        $this->editor->save();

        $content = file_get_contents($this->envFile);
        $this->assertStringContainsString('NEW_KEY=saved_value', $content);
        $this->assertFalse($this->editor->hasChanged());
    }

    #[Test]
    public function preservesExistingEntriesOnSave(): void
    {
        $this->editor->setKey('NEW_KEY', 'val');
        $this->editor->save();

        $content = file_get_contents($this->envFile);
        $this->assertStringContainsString('APP_NAME=Laravel', $content);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $content);
        $this->assertStringContainsString('NEW_KEY=val', $content);
    }

    #[Test]
    public function returnsSelfForFluentApi(): void
    {
        $result = $this->editor
            ->setKey('A', '1')
            ->setKey('B', '2')
            ->addEmpty()
            ->addComment('test')
            ->deleteKey('DB_PORT');

        $this->assertInstanceOf(DotenvEditor::class, $result);
    }

    #[Test]
    public function tracksChangedState(): void
    {
        $this->assertFalse($this->editor->hasChanged());
        $this->editor->setKey('FOO', 'bar');
        $this->assertTrue($this->editor->hasChanged());
        $this->editor->save();
        $this->assertFalse($this->editor->hasChanged());
    }

    #[Test]
    public function getsEntries(): void
    {
        $entries = $this->editor->getEntries(false);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries);
        $this->assertArrayHasKey('raw_data', $entries[0]);
    }

    #[Test]
    public function getsEntriesWithParsedData(): void
    {
        $entries = $this->editor->getEntries(true);
        $this->assertArrayHasKey('parsed_data', $entries[0]);
        $this->assertSame('setter', $entries[0]['parsed_data']['type']);
    }

    #[Test]
    public function loadsADifferentFile(): void
    {
        $otherFile = $this->tmpDir . '/.env.other';
        file_put_contents($otherFile, "OTHER_KEY=other_value\n");

        $this->editor->load($otherFile);
        $this->assertTrue($this->editor->keyExists('OTHER_KEY'));
        $this->assertSame('other_value', $this->editor->getValue('OTHER_KEY'));
    }

    #[Test]
    public function createsBackupAndListsBackups(): void
    {
        $this->app['config']->set('dotenv-editor.backupPath', $this->tmpDir . '/backups');
        $editor = $this->app->make('dotenv-editor');
        $editor->autoBackup(false);
        $editor->load($this->envFile);

        $editor->backup();
        $backups = $editor->getBackups();

        $this->assertNotEmpty($backups);
        $this->assertArrayHasKey('filename', $backups[0]);
        $this->assertArrayHasKey('filepath', $backups[0]);
        $this->assertArrayHasKey('created_at', $backups[0]);
    }

    #[Test]
    public function restoresFromBackup(): void
    {
        $this->app['config']->set('dotenv-editor.backupPath', $this->tmpDir . '/backups');
        $editor = $this->app->make('dotenv-editor');
        $editor->autoBackup(false);
        $editor->load($this->envFile);

        $editor->backup();

        // Modify the file
        $editor->setKey('APP_NAME', 'Modified');
        $editor->save();
        $this->assertSame('Modified', $editor->getValue('APP_NAME'));

        // Restore
        $editor->restore();
        $this->assertSame('Laravel', $editor->getValue('APP_NAME'));
    }

    #[Test]
    public function setsSetterComment(): void
    {
        $this->editor->setSetterComment('APP_NAME', 'app name comment');
        $this->assertTrue($this->editor->hasChanged());
    }

    #[Test]
    public function clearsSetterComment(): void
    {
        $this->editor->setKey('APP_NAME', 'Laravel', 'has comment');
        $this->editor->clearSetterComment('APP_NAME');
        $this->assertTrue($this->editor->hasChanged());
    }

    #[Test]
    public function setsExportOnSetter(): void
    {
        $this->editor->setExportSetter('APP_NAME', true);
        $this->assertTrue($this->editor->hasChanged());
        $buffer = $this->editor->getBuffer();
        $entry = array_values(array_filter($buffer, fn($e) => ($e['key'] ?? '') === 'APP_NAME'));
        $this->assertTrue($entry[0]['export']);
    }
}
