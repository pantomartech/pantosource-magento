<?php
declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$stubsRoot = __DIR__ . '/stubs';

spl_autoload_register(static function (string $class) use ($moduleRoot, $stubsRoot): void {
    $prefix = 'Panto\\Source\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = $moduleRoot . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
    $stub = $stubsRoot . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($stub)) {
        require_once $stub;
    }
});
