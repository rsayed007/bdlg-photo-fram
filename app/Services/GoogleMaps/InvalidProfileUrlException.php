<?php

namespace App\Services\GoogleMaps;

use RuntimeException;

/**
 * Thrown by ContribIdResolver. The message is a translation key so it can be
 * shown to the user as-is via __().
 */
class InvalidProfileUrlException extends RuntimeException
{
    public static function noUrl(): self
    {
        return new self('resolver.no_url');
    }

    public static function disallowedHost(): self
    {
        return new self('resolver.disallowed_host');
    }

    public static function notContribUrl(): self
    {
        return new self('resolver.not_contrib_url');
    }

    public static function insecure(): self
    {
        return new self('resolver.insecure');
    }

    public static function tooManyRedirects(): self
    {
        return new self('resolver.too_many_redirects');
    }

    public static function unreachable(): self
    {
        return new self('resolver.unreachable');
    }

    public function userMessage(): string
    {
        return __($this->getMessage());
    }
}
