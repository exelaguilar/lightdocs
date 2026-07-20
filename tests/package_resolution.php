<?php

declare(strict_types=1);

/**
 * Package-source resolution test for the TinyMVC extraction (Phase 1 core,
 * plus the Phase A Url/RoutePattern batch consumed from v0.2.0).
 *
 * Invocation: php tests/package_resolution.php
 *
 * Proves that every class physically moved into the `tiny-mvc-framework`
 * private package resolve from the package's owner-approved
 * system/engine|helper|library layout (via Composer's classmap autoloader,
 * e.g. System\Engine\Action -> <composer-install>/system/engine/action.php,
 * System\Helper\ClientIp -> <composer-install>/system/helper/client_ip.php,
 * System\Library\Document -> <composer-install>/system/library/document.php)
 * — not from the package's superseded src/Engine|Helper|Library layout, and
 * not from a stale local copy under Lightdocs' former system/engine,
 * system/helper, or system/library trees.
 *
 * This script only requires system/startup.php (which registers Composer's
 * autoloader), not the full system/framework.php bootstrap — resolving these
 * classes is a pure autoloading question and does not need Registry,
 * Config, a database, or anything else the fuller bootstrap constructs.
 *
 * For every class this checks:
 *   1. class_exists()
 *   2. ReflectionClass construction succeeds
 *   3. the reflected file is inside the tiny-mvc-framework package
 *   4. the reflected file's basename is the expected lowercase filename
 *   5. the reflected file is NOT inside Lightdocs' former local
 *      system/engine, system/helper, or system/library paths
 *   6. the former local source path is absent from disk
 *   7. the reflected namespace (via getNamespaceName()) is unchanged
 *   8. the reflected short class name (via getShortName()) is unchanged
 *   9. the reflected file is NOT inside the package's superseded src/ layout
 *  10. the reflected file is the exact expected system/engine, system/helper,
 *      or system/library path inside the installed package
 *  11. the installed package is not the conventional sibling checkout
 *
 * Before the local files are deleted, check 6 (and therefore 5) is
 * expected to report "local file still present" for every class — that is
 * the known, correct pre-deletion state, not a bug in this script. Checks
 * 1-4/7/8/9 (does Composer's classmap actually resolve each class to the
 * package?) are what gate the deletion step. Run this script again after
 * deletion: every one of the 9 checks must pass for the batch to be complete.
 *
 * Exit 0 only if every check for every class passes. Any failure prints to
 * STDERR and exits 1.
 */

require dirname(__DIR__) . '/upload/system/startup.php';

$packageInstallPath = Composer\InstalledVersions::getInstallPath('exelaguilar/tiny-mvc-framework-private');

if ($packageInstallPath === null) {
    fwrite(STDERR, "TinyMVC is not installed through Composer.\n");
    exit(1);
}

$packageRoot = realpath($packageInstallPath);
$packageSrcLegacy = ($packageRoot !== false ? $packageRoot : $packageInstallPath) . DIRECTORY_SEPARATOR . 'src';
$siblingPackage = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tiny-mvc-framework';
$siblingPackageRoot = realpath($siblingPackage) ?: $siblingPackage;
$localEngine = realpath(DIR_SYSTEM . 'engine') ?: (DIR_SYSTEM . 'engine');
$localHelper = realpath(DIR_SYSTEM . 'helper') ?: (DIR_SYSTEM . 'helper');
$localLibrary = realpath(DIR_SYSTEM . 'library') ?: (DIR_SYSTEM . 'library');

/** @var array<string, array{basename: string, formerLocalPath: string}> $classes */
$classes = [
    'System\\Engine\\Action' => ['basename' => 'action.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/action.php'],
    'System\\Engine\\CallbackAction' => ['basename' => 'callback_action.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/callback_action.php'],
    'System\\Engine\\Config' => ['basename' => 'config.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/config.php'],
    'System\\Engine\\Controller' => ['basename' => 'controller.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/controller.php'],
    'System\\Engine\\Event' => ['basename' => 'event.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/event.php'],
    'System\\Engine\\Factory' => ['basename' => 'factory.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/factory.php'],
    'System\\Engine\\Front' => ['basename' => 'front.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/front.php'],
    'System\\Engine\\Loader' => ['basename' => 'loader.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/loader.php'],
    'System\\Engine\\Model' => ['basename' => 'model.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/model.php'],
    'System\\Engine\\Proxy' => ['basename' => 'proxy.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/proxy.php'],
    'System\\Engine\\Registry' => ['basename' => 'registry.php', 'formerLocalPath' => DIR_SYSTEM . 'engine/registry.php'],
    'System\\Helper\\ClientIp' => ['basename' => 'client_ip.php', 'formerLocalPath' => DIR_SYSTEM . 'helper/client_ip.php'],
    'System\\Helper\\RouteMatcher' => ['basename' => 'route_matcher.php', 'formerLocalPath' => DIR_SYSTEM . 'helper/route_matcher.php'],
    'System\\Helper\\RoutePattern' => ['basename' => 'route_pattern.php', 'formerLocalPath' => DIR_SYSTEM . 'helper/route_pattern.php'],
    'System\\Library\\Request' => ['basename' => 'request.php', 'formerLocalPath' => DIR_SYSTEM . 'library/request.php'],
    'System\\Library\\Response' => ['basename' => 'response.php', 'formerLocalPath' => DIR_SYSTEM . 'library/response.php'],
    'System\\Library\\Document' => ['basename' => 'document.php', 'formerLocalPath' => DIR_SYSTEM . 'library/document.php'],
    'System\\Library\\Language' => ['basename' => 'language.php', 'formerLocalPath' => DIR_SYSTEM . 'library/language.php'],
    'System\\Library\\Log' => ['basename' => 'log.php', 'formerLocalPath' => DIR_SYSTEM . 'library/log.php'],
    'System\\Library\\Session' => ['basename' => 'session.php', 'formerLocalPath' => DIR_SYSTEM . 'library/session.php'],
    'System\\Library\\Template' => ['basename' => 'template.php', 'formerLocalPath' => DIR_SYSTEM . 'library/template.php'],
    'System\\Library\\Url' => ['basename' => 'url.php', 'formerLocalPath' => DIR_SYSTEM . 'library/url.php'],
];

$failures = [];
$rows = [];

foreach ($classes as $fqcn => $expect) {
    $row = ['class' => $fqcn, 'resolved' => '(none)'];

    if (!class_exists($fqcn, true)) {
        $failures[] = "{$fqcn}: class_exists() returned false — did not resolve at all.";
        $rows[] = $row;
        continue;
    }

    try {
        $reflection = new ReflectionClass($fqcn);
    } catch (Throwable $e) {
        $failures[] = "{$fqcn}: ReflectionClass construction failed: {$e->getMessage()}";
        $rows[] = $row;
        continue;
    }

    $file = $reflection->getFileName();
    $row['resolved'] = $file !== false ? $file : '(no file — internal/eval)';

    if ($file === false) {
        $failures[] = "{$fqcn}: reflected class has no source file.";
        $rows[] = $row;
        continue;
    }

    $realFile = realpath($file) ?: $file;

    $normalizedFile = str_replace('\\', '/', $realFile);
    $normalizedPackageRoot = rtrim(str_replace('\\', '/', (string) $packageRoot), '/');
    $normalizedSiblingRoot = rtrim(str_replace('\\', '/', $siblingPackageRoot), '/');

    // Check 3 + 5: inside the package, not inside any former local tree.
    $insidePackage = $packageRoot !== false && str_starts_with($normalizedFile, $normalizedPackageRoot . '/');
    $insideLocalEngine = str_starts_with($realFile, $localEngine);
    $insideLocalHelper = str_starts_with($realFile, $localHelper);
    $insideLocalLibrary = str_starts_with($realFile, $localLibrary);

    if (!$insidePackage) {
        $failures[] = "{$fqcn}: resolved file is not inside the tiny-mvc-framework package ({$realFile}).";
    }
    if (str_starts_with($normalizedFile, $normalizedSiblingRoot . '/')) {
        $failures[] = "{$fqcn}: resolved file is inside the sibling tiny-mvc-framework checkout ({$realFile}).";
    }
    if ($insideLocalEngine || $insideLocalHelper || $insideLocalLibrary) {
        $failures[] = "{$fqcn}: resolved file is inside a former local system/ path ({$realFile}).";
    }

    // Check 9: not inside the package's superseded src/Engine|Helper|Library layout.
    if (str_starts_with($realFile, $packageSrcLegacy . DIRECTORY_SEPARATOR) || str_starts_with($realFile, $packageSrcLegacy . '/')) {
        $failures[] = "{$fqcn}: resolved file is inside the superseded src/ layout ({$realFile}) — expected system/engine|helper|library.";
    }

    // Check 4: exact lowercase basename preserved.
    if (basename($realFile) !== $expect['basename']) {
        $failures[] = "{$fqcn}: expected basename '{$expect['basename']}', got '" . basename($realFile) . "'.";
    }

    $namespaceParts = explode('\\', $fqcn);
    $expectedGroup = strtolower($namespaceParts[1] ?? '');
    $expectedPackageFile = $normalizedPackageRoot . '/system/' . $expectedGroup . '/' . $expect['basename'];
    if ($normalizedFile !== $expectedPackageFile) {
        $failures[] = "{$fqcn}: expected exact package path '{$expectedPackageFile}', got '{$normalizedFile}'.";
    }

    // Check 6: former local source path must be absent from disk.
    $formerLocalExists = is_file($expect['formerLocalPath']);
    $row['former_local_exists'] = $formerLocalExists ? 'yes (pre-deletion state, or deletion incomplete)' : 'no (deleted)';
    if ($formerLocalExists) {
        $failures[] = "{$fqcn}: former local source path still exists on disk ({$expect['formerLocalPath']}).";
    }

    // Check 7 + 8: namespace and short class name unchanged.
    $expectedNamespace = substr($fqcn, 0, strrpos($fqcn, '\\'));
    $expectedShortName = substr($fqcn, strrpos($fqcn, '\\') + 1);
    if ($reflection->getNamespaceName() !== $expectedNamespace) {
        $failures[] = "{$fqcn}: namespace changed — reflected '{$reflection->getNamespaceName()}'.";
    }
    if ($reflection->getShortName() !== $expectedShortName) {
        $failures[] = "{$fqcn}: short class name changed — reflected '{$reflection->getShortName()}'.";
    }

    $rows[] = $row;
}

foreach ($rows as $row) {
    printf("%-28s -> %s\n", $row['class'], $row['resolved']);
}

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'All ' . count($classes) . ' classes resolve from the installed tiny-mvc-framework/system/{engine,helper,library} paths with lowercase filenames preserved, no sibling or src/ resolution, and no local duplicates.' . PHP_EOL;
