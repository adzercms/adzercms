<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

interface Exchange
{
    /**
     * @param array<string, string> $form
     * @param array<string, string> $headers
     *
     * @throws LoginFailed
     */
    public function post(string $url, array $form, array $headers = []): Answer;

    /**
     * @param array<string, string> $headers
     *
     * @throws LoginFailed
     */
    public function get(string $url, array $headers = []): Answer;

    /**
     * @param array<string, string> $headers
     *
     * @throws LoginFailed
     */
    public function status(string $method, string $url, array $headers = []): int;

    /** @throws LoginFailed */
    public function claims(string $token, string $keys, string $issuer, string $audience): Answer;
}
