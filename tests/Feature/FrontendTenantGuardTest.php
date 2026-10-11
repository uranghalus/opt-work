<?php

/**
 * Static guard for the "Cannot read properties of undefined (reading
 * 'toString')" crash class: the shared activeTenant prop is nullable
 * server-side, so any non-null assertion on it in page/component code defeats
 * the type system and crashes route helpers in the browser. Pages must use
 * useRequiredTenant() (resources/js/lib/tenant.ts) instead.
 */
it('never asserts non-null on the shared activeTenant prop', function () {
    $roots = [
        resource_path('js/pages'),
        resource_path('js/components'),
        resource_path('js/layouts'),
    ];

    $violations = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                continue;
            }

            if (str_contains($content, 'activeTenant!')) {
                $violations[] = str_replace(resource_path('js').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($violations)->toBe([], 'Non-null assertions on activeTenant defeat the type system and crash route helpers. Use useRequiredTenant() instead.');
});
