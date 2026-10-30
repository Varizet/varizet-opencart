<?php

// Loads the bundled SDK and the shared Varizet\OpenCart classes (system/library/varizet/).
if (!class_exists(\Varizet\VarizetClient::class)) {
    require_once __DIR__ . '/lib/varizet-php/autoload.php';
}
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Varizet\\OpenCart\\', 17) === 0) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 17)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
