<?php

(static function (string $root): void {
    if ('cli' === \PHP_SAPI && getenv('ADZERCMS_ENTRY') === $root) {
        return;
    }

    $locks = $root.'/var/lock';
    is_dir($locks) || @mkdir($locks, 0777, true);
    [$hold, $entry] = array_map(
        static fn (string $name) => fopen($locks.'/'.$name, 'c') ?: throw new RuntimeException(sprintf('lock %s of the instance is not opened', $name)),
        ['refresh', 'entry'],
    );
    $request = 'cli' !== \PHP_SAPI;

    if (!flock($hold, $request ? \LOCK_SH | \LOCK_NB : \LOCK_SH)) {
        $request || throw new RuntimeException('hold of the instance is not awaited');
        http_response_code(503);
        header('Retry-After: 30');
        exit;
    }
    flock($entry, \LOCK_SH);
    fclose($hold);

    $GLOBALS['adzercms.entry'] = $entry;
    $_SERVER['ADZERCMS_ENTRY'] = $root;
    putenv('ADZERCMS_ENTRY='.$root);
})(dirname(__DIR__));
