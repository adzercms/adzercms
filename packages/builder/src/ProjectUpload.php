<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\MenuForm;
use AdzerCMS\Pages\PageForm;
use AdzerCMS\Pages\Pictures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

use function Symfony\Component\Translation\t;

final readonly class ProjectUpload
{
    public function __construct(
        private ProjectFile $file,
        private PageForm $form,
        private MenuForm $menu,
        private Pictures $pictures,
        private Blocks $types,
        private PageDocument $document,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return list<Page>|Refusal */
    public function upload(File $file): array|Refusal
    {
        $read = $this->file->read($file);
        if ($read instanceof Refusal) {
            return $read;
        }
        [$pages, $names] = $read;
        $titles = [];
        foreach ($pages as $page) {
            $title = $this->form->title($page['title']);
            if ($title instanceof Refusal) {
                return $title;
            }
            $titles[] = [$title, $page['path']];
        }
        $taken = $this->form->taken(array_column($pages, 'path'));
        if ([] !== $taken) {
            return new Refusal(t('builder.refusal.taken', ['paths' => implode(', ', $taken)]));
        }
        $stored = $this->file->pictures($file, $names, $this->pictures);
        if ($stored instanceof Refusal) {
            return $stored;
        }

        $blocks = [];
        foreach (ProjectFile::renamed($pages, $stored) as $at => $page) {
            foreach ($page['blocks'] as $block) {
                $type = $this->types->find($block['type']);
                $values = null === $type ? $block['values'] : $this->document->stored($type, $block['values'], $this->pictures);
                if ($values instanceof Refusal) {
                    $this->pictures->drop(array_values($stored));

                    return $values;
                }
                $blocks[$at][] = [$block['type'], $values, $block['style']];
            }
        }

        $added = $this->form->append($titles);
        foreach ($added as $at => $page) {
            foreach ($blocks[$at] ?? [] as $position => [$type, $values, $style]) {
                $block = new PageBlock($page, $type, $values);
                $block->restyle($style);
                $block->place($position + 1);
                $this->em->persist($block);
            }
        }
        $this->menu->append($added);
        $this->em->flush();

        return $added;
    }
}
