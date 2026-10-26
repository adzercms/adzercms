<?php

declare(strict_types=1);

$ask = static function (string $question, ?string $default, string $pattern): string {
    while (true) {
        fwrite(\STDOUT, null === $default ? $question.': ' : \sprintf('%s [%s]: ', $question, $default));
        $line = fgets(\STDIN);
        if (false === $line) {
            fwrite(\STDERR, \sprintf("no answer to: %s\n", $question));
            exit(1);
        }
        $answer = '' === trim($line) ? (string) $default : trim($line);
        if (1 === preg_match($pattern, $answer)) {
            return $answer;
        }
        fwrite(\STDERR, \sprintf("%s doesn't fit %s\n", $answer, $pattern));
    }
};
$studly = static fn (string $name): string => str_replace(['-', '_', '.'], '', ucwords($name, '-_.'));

$package = $ask('Package name', null, '~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~');
[$vendor, $name] = explode('/', $package);
$namespace = $ask('Namespace', $studly($vendor).'\\'.$studly($name), '~^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$~');
$bundle = $ask('Bundle class', $studly($name).'Bundle', '~^[A-Z][A-Za-z0-9]*Bundle$~');
$key = substr($bundle, 0, -\strlen('Bundle'));
$description = $ask('What the module does, in English', null, '~\S~');
$russian = $ask('What the module does, in Russian', null, '~\S~');

$manifest = json_decode((string) file_get_contents(__DIR__.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
$manifest = ['name' => $package, 'type' => 'adzercms-module', 'description' => $description] + array_diff_key(\is_array($manifest) ? $manifest : [], array_flip(['description', 'keywords', 'scripts']));
$manifest['autoload'] = ['psr-4' => [$namespace.'\\' => 'src/']];
$manifest['extra'] = ['adzercms' => ['bundle' => $namespace.'\\'.$bundle, 'description' => ['ru' => $russian]]];
file_put_contents(__DIR__.'/composer.json', json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");

$names = [
    'Vendor\\Module' => $namespace,
    'ModuleBundle' => $bundle,
    'ModuleTest' => $key.'Test',
];
foreach ([
    'src/ModuleBundle.php' => 'src/'.$bundle.'.php',
    'tests/ModuleTest.php' => 'tests/'.$key.'Test.php',
] as $file => $target) {
    file_put_contents(__DIR__.'/'.$target, strtr((string) file_get_contents(__DIR__.'/'.$file), $names));
    unlink(__DIR__.'/'.$file);
}
unlink(__FILE__);

fwrite(\STDOUT, \sprintf("%s is ready: the module key is %s\n", $package, $key));
