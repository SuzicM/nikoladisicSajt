<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Belgrade');

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__pass']++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $GLOBALS['__fail']++;
        echo "  ✗ {$name}\n    {$e->getMessage()}\n";
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg !== '' ? "{$msg}: " : '')
            . 'očekivano ' . var_export($expected, true)
            . ', dobijeno ' . var_export($actual, true));
    }
}

function assert_true(bool $cond, string $msg = 'uslov nije ispunjen'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function tmp_dir(): string
{
    $dir = sys_get_temp_dir() . '/nd-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    return $dir;
}

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/*_test.php');
sort($files);
foreach ($files as $file) {
    if ($filter !== '' && !str_contains(basename($file), $filter)) {
        continue;
    }
    echo basename($file) . "\n";
    require $file;
}

echo "\n{$GLOBALS['__pass']} prošlo, {$GLOBALS['__fail']} palo\n";
exit($GLOBALS['__fail'] > 0 ? 1 : 0);
