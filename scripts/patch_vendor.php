<?php
declare(strict_types=1);

// The legacy UI returns message arrays from AJAX controllers. ThinkPHP 6.1
// otherwise renders those arrays as HTML unless the Accept header is JSON.
$dispatchFile = dirname(__DIR__) . '/vendor/topthink/framework/src/think/route/Dispatch.php';
if (!is_file($dispatchFile)) {
    fwrite(STDERR, "ThinkPHP Dispatch.php was not found.\n");
    exit(1);
}

$contents = file_get_contents($dispatchFile);
if (!is_string($contents)) {
    fwrite(STDERR, "Unable to read ThinkPHP Dispatch.php.\n");
    exit(1);
}

$original = '$type     = $this->request->isJson() ? \'json\' : \'html\';';
$replacement = '$type     = ($this->request->isJson() || $this->request->isAjax()) ? \'json\' : \'html\';';
if (strpos($contents, $replacement) === false) {
    if (strpos($contents, $original) === false) {
        fwrite(STDERR, "ThinkPHP response dispatch changed; refusing an unsafe vendor patch.\n");
        exit(1);
    }

    $patched = str_replace($original, $replacement, $contents, $count);
    if ($count !== 1 || file_put_contents($dispatchFile, $patched) === false) {
        fwrite(STDERR, "Unable to apply the ThinkPHP AJAX response compatibility patch.\n");
        exit(1);
    }
}

// ThinkPHP 6.1.2 predates the one-line upstream exception-template XSS fix
// (top-think/framework commit 57d1950a1844ef8d3098ea290032aeb92e2e32c3).
$exceptionFile = dirname(__DIR__) . '/vendor/topthink/framework/src/tpl/think_exception.tpl';
if (!is_file($exceptionFile)) {
    fwrite(STDERR, "ThinkPHP exception template was not found.\n");
    exit(1);
}
$exceptionContents = file_get_contents($exceptionFile);
$xssOriginal = '$result[] = is_int($key) ? $value : "\'{$key}\' => {$value}";';
$xssReplacement = '$result[] = is_int($key) ? $value : sprintf(\'\\\'%s\\\' => %s\', htmlentities($key), $value);';
if (strpos((string)$exceptionContents, $xssReplacement) === false) {
    if (strpos((string)$exceptionContents, $xssOriginal) === false) {
        fwrite(STDERR, "ThinkPHP exception template changed; refusing an unsafe vendor patch.\n");
        exit(1);
    }
    $patchedException = str_replace($xssOriginal, $xssReplacement, (string)$exceptionContents, $xssCount);
    if ($xssCount !== 1 || file_put_contents($exceptionFile, $patchedException) === false) {
        fwrite(STDERR, "Unable to apply the ThinkPHP exception-template XSS patch.\n");
        exit(1);
    }
}

// Old releases cached addon listeners generated from removed entries in
// config/addons.php. Those listeners contain an empty class name and make
// every mail hook fail with "class not exists:" even after the code is fixed.
// Validate the cached listener tuples before registering them so an upgrade
// can discard stale cache and rebuild it from the currently installed addons.
$addonServiceFile = dirname(__DIR__) . '/vendor/zzstudio/think-addons/src/addons/Service.php';
if (!is_file($addonServiceFile)) {
    fwrite(STDERR, "think-addons Service.php was not found.\n");
    exit(1);
}
$addonServiceContents = file_get_contents($addonServiceFile);
$addonCacheNeedle = <<<'PHP'
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if (empty($hooks)) {
PHP;
$addonCacheReplacement = <<<'PHP'
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        // Cached addon listeners can outlive removed plugins or legacy hook
        // configuration. Reject malformed/stale tuples before Event sees them.
        if (!empty($hooks)) {
            foreach ($hooks as $listeners) {
                foreach ((array) $listeners as $listener) {
                    if (!is_array($listener)
                        || count($listener) < 2
                        || !is_string($listener[0])
                        || $listener[0] === ''
                        || !class_exists($listener[0])
                        || !method_exists($listener[0], (string) $listener[1])
                    ) {
                        $hooks = [];
                        Cache::delete('hooks');
                        break 2;
                    }
                }
            }
        }
        if (empty($hooks)) {
PHP;
if (strpos((string)$addonServiceContents, 'Cached addon listeners can outlive') === false) {
    if (strpos((string)$addonServiceContents, $addonCacheNeedle) === false) {
        fwrite(STDERR, "think-addons hook loader changed; refusing an unsafe vendor patch.\n");
        exit(1);
    }
    $patchedAddonService = str_replace(
        $addonCacheNeedle,
        $addonCacheReplacement,
        (string)$addonServiceContents,
        $addonCacheCount
    );
    if ($addonCacheCount !== 1 || file_put_contents($addonServiceFile, $patchedAddonService) === false) {
        fwrite(STDERR, "Unable to apply the think-addons stale hook cache patch.\n");
        exit(1);
    }
}
