<?php
declare(strict_types=1);

$moduleRoot = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    $prefix = 'Panto\\Source\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = $moduleRoot . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
