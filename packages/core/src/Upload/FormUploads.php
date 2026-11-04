<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Upload;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class FormUploads
{
    public function __construct(private Uploads $uploads)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST') || !$request->request->has(Uploads::FIELD) || !$request->hasSession()) {
            return;
        }

        $keys = $request->request->all(Uploads::FIELD);
        $request->request->remove(Uploads::FIELD);
        $files = [];
        foreach ($keys as $key) {
            $taken = \is_string($key) ? $this->uploads->take($key, $request->getSession()->getId()) : null;
            if (null !== $taken) {
                parse_str(rawurlencode($taken[0]).'=', $tree);
                $files = array_merge_recursive($files, self::place($tree, $taken[1]));
            }
        }

        foreach ($files as $name => $value) {
            $sent = $request->files->get((string) $name);
            $request->files->set((string) $name, \is_array($sent) && \is_array($value) ? array_replace_recursive($sent, $value) : $value);
        }
    }

    /**
     * @param array<mixed> $tree
     *
     * @return array<mixed>
     */
    private static function place(array $tree, UploadedFile $file): array
    {
        return array_map(static fn (mixed $leaf): mixed => \is_array($leaf) ? self::place($leaf, $file) : $file, $tree);
    }
}
