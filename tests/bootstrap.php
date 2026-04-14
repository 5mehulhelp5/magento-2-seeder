<?php

declare(strict_types=1);

/**
 * Stub Magento framework classes so unit tests can run outside a Magento installation.
 */
if (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
    eval('
        namespace Magento\Framework\Component;
        class ComponentRegistrar {
            public const MODULE = "module";
            public static function register(string $type, string $name, string $path): void {}
        }
    ');
}

if (!interface_exists(\Magento\Framework\ObjectManagerInterface::class)) {
    eval('
        namespace Magento\Framework;
        interface ObjectManagerInterface {
            public function create(string $type, array $arguments = []): object;
            public function get(string $type): object;
            public function configure(array $configuration): void;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\Filesystem\DirectoryList::class)) {
    eval('
        namespace Magento\Framework\App\Filesystem;
        class DirectoryList {
            public function getRoot(): string { return ""; }
            public function getPath(string $code): string { return ""; }
        }
    ');
}

require dirname(__DIR__) . '/vendor/autoload.php';
