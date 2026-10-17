<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Keychain;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

trait Browser
{
    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const CONTAINER_PASSWORD = 'container-password';

    protected function formToken(string $id, ?KernelBrowser $browser = null): string
    {
        $browser ??= $this->client;
        $session = self::service(SessionFactoryInterface::class, 'session.factory')->createSession();
        $cookie = $browser->getCookieJar()->get($session->getName());
        if (null !== $cookie) {
            $session->setId($cookie->getValue());
        }

        $request = new Request();
        $request->setSession($session);
        $stack = self::service(RequestStack::class);
        $stack->push($request);
        try {
            $token = self::service(CsrfTokenManagerInterface::class, 'security.csrf.token_manager')->getToken($id)->getValue();
        } finally {
            $stack->pop();
        }

        $session->save();
        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        return $token;
    }

    protected function signIn(string $login, string $password, ?KernelBrowser $browser = null): void
    {
        $browser ??= $this->client;
        $browser->request('POST', '/login', [
            '_username' => $login,
            '_password' => $password,
            '_csrf_token' => $this->formToken('authenticate', $browser),
        ]);
    }

    protected function browser(string $agent, string $address): KernelBrowser
    {
        $browser = self::service(KernelBrowser::class, 'test.client');
        $browser->disableReboot();
        $browser->setServerParameter('HTTP_USER_AGENT', $agent);
        $browser->setServerParameter('REMOTE_ADDR', $address);

        return $browser;
    }

    protected function arrive(string $url): Crawler
    {
        $page = $this->client->request('GET', $url);
        while ($this->client->getResponse()->isRedirection()) {
            $page = $this->client->followRedirect();
        }

        return $page;
    }

    protected function fragment(string $url, ?string $page = null, bool $rescan = false): Crawler
    {
        return $this->client->request('GET', $url, server: ['HTTP_HX-Request' => 'true']
            + (null === $page ? [] : ['HTTP_HX-Current-URL' => $page])
            + ($rescan ? ['HTTP_'.Snapshot::RESCAN => '1'] : []));
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files
     */
    protected function post(string $url, array $fields = [], array $files = []): Crawler
    {
        return $this->client->request('POST', $url, $fields + [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID)], $files);
    }

    /** @param array<string, mixed> $values */
    protected function submitWith(Form $form, array $values): Crawler
    {
        return $this->client->request($form->getMethod(), $form->getUri(), array_replace_recursive($form->getPhpValues(), $values));
    }

    protected static function cell(Crawler $listing, string $row, string $column): Crawler
    {
        $index = array_search($column, $listing->filter('thead th')->each(static fn (Crawler $head): string => trim($head->text())), true);
        $line = $listing->filter('tbody tr')->reduce(static fn (Crawler $line): bool => str_contains($line->text(), $row));

        return \is_int($index) && $line->count() > 0
            ? $line->first()->filter('td')->eq($index)
            : self::fail(\sprintf('the listing carries no cell %s of the row %s', $column, $row));
    }

    protected static function tile(Crawler $page, string $label): string
    {
        foreach ($page->filter('.info-item, .stat-card') as $node) {
            $tile = new Crawler($node);
            if ($label === trim($tile->filter('.info-label, .stat-label')->text(''))) {
                return trim($tile->filter('.info-value, .stat-value')->text());
            }
        }

        return self::fail(\sprintf('the page carries no tile %s', $label));
    }

    protected static function toast(Crawler $page, string $kind): string
    {
        $toast = $page->filter('.toast-'.$kind);

        return 0 === $toast->count() ? self::fail(\sprintf('the page carries no %s toast', $kind)) : trim($toast->text());
    }

    protected function scratchFile(string $name, string $content): UploadedFile
    {
        $directory = self::parameter('adzercms.files_dir').'/scratch';
        (new Filesystem())->dumpFile($directory.'/'.$name, $content);

        return new UploadedFile($directory.'/'.$name, $name, null, null, true);
    }

    protected function picture(string $name): UploadedFile
    {
        return $this->scratchFile($name, (string) base64_decode(self::PNG, true));
    }

    protected function keepContainer(string $setting): void
    {
        $field = self::service(Catalog::class)->fields()[$setting] ?? self::fail(\sprintf('setting %s is not in the catalog', $setting));

        self::assertNull(self::service(Keychain::class)->store($field, $this->scratchFile($field->name.'.p12', $this->container()), self::CONTAINER_PASSWORD));
    }

    protected function container(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $request = openssl_csr_new(['commonName' => 'cabinet.test'], $key);
        self::assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $request);
        $certificate = openssl_csr_sign($request, null, $key, 1);
        self::assertNotFalse($certificate);
        self::assertTrue(openssl_pkcs12_export($certificate, $container, $key, self::CONTAINER_PASSWORD));

        return $container;
    }
}
