<?php
declare(strict_types=1);

/**
 * Minimal, real, interface-satisfying PSR-7 stubs — used ONLY to invoke the
 * actual public HTTP-handler methods (RBACHandler::createPermission() etc.)
 * end-to-end in a test, exactly as the real Slim/PSR-7 runtime would, without
 * needing a full web server or the slim/psr7 package (unreachable via
 * packagist in this sandboxed network — psr/http-message itself was pulled
 * directly from github.com/php-fig/http-message, an allowlisted host).
 *
 * These implement the REAL Psr\Http\Message interfaces (cloned from the
 * canonical repo) — not reimplementations — so any type-hint check against
 * ServerRequestInterface/ResponseInterface passes exactly as it would in
 * production.
 */

final class StubStream implements \Psr\Http\Message\StreamInterface
{
    private string $contents = '';
    public function __toString(): string { return $this->contents; }
    public function close(): void {}
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->contents); }
    public function tell(): int { return 0; }
    public function eof(): bool { return true; }
    public function isSeekable(): bool { return false; }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool { return true; }
    public function write(string $string): int { $this->contents .= $string; return strlen($string); }
    public function isReadable(): bool { return true; }
    public function read(int $length): string { return $this->contents; }
    public function getContents(): string { return $this->contents; }
    public function getMetadata(?string $key = null) { return null; }
}

final class StubResponse implements \Psr\Http\Message\ResponseInterface
{
    public int $statusCode = 200;
    public string $reasonPhrase = '';
    private array $headers = [];
    private StubStream $body;

    public function __construct() { $this->body = new StubStream(); }

    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): \Psr\Http\Message\MessageInterface { return $this; }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
    public function getHeader(string $name): array { return $this->headers[$name] ?? []; }
    public function getHeaderLine(string $name): string { return implode(',', $this->headers[$name] ?? []); }
    public function withHeader(string $name, $value): \Psr\Http\Message\MessageInterface
    {
        $clone = clone $this;
        $clone->headers[$name] = (array) $value;
        return $clone;
    }
    public function withAddedHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this->withHeader($name, $value); }
    public function withoutHeader(string $name): \Psr\Http\Message\MessageInterface
    {
        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }
    public function getBody(): \Psr\Http\Message\StreamInterface { return $this->body; }
    public function withBody(\Psr\Http\Message\StreamInterface $body): \Psr\Http\Message\MessageInterface { return $this; }
    public function getStatusCode(): int { return $this->statusCode; }
    public function withStatus(int $code, string $reasonPhrase = ''): \Psr\Http\Message\ResponseInterface
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }
    public function getReasonPhrase(): string { return $this->reasonPhrase; }
}

final class StubUri implements \Psr\Http\Message\UriInterface
{
    public function getScheme(): string { return 'http'; }
    public function getAuthority(): string { return ''; }
    public function getUserInfo(): string { return ''; }
    public function getHost(): string { return 'test'; }
    public function getPort(): ?int { return null; }
    public function getPath(): string { return '/'; }
    public function getQuery(): string { return ''; }
    public function getFragment(): string { return ''; }
    public function withScheme(string $scheme): \Psr\Http\Message\UriInterface { return $this; }
    public function withUserInfo(string $user, ?string $password = null): \Psr\Http\Message\UriInterface { return $this; }
    public function withHost(string $host): \Psr\Http\Message\UriInterface { return $this; }
    public function withPort(?int $port): \Psr\Http\Message\UriInterface { return $this; }
    public function withPath(string $path): \Psr\Http\Message\UriInterface { return $this; }
    public function withQuery(string $query): \Psr\Http\Message\UriInterface { return $this; }
    public function withFragment(string $fragment): \Psr\Http\Message\UriInterface { return $this; }
    public function __toString(): string { return 'http://test/'; }
}

final class StubServerRequest implements \Psr\Http\Message\ServerRequestInterface
{
    private array $attributes = [];
    private array $headers = [];
    private $parsedBody = null;
    private StubStream $body;

    public function __construct(array $attributes = [], array $headers = [], $parsedBody = null)
    {
        $this->attributes = $attributes;
        $this->headers = $headers;
        $this->parsedBody = $parsedBody;
        $this->body = new StubStream();
    }

    public function getServerParams(): array { return []; }
    public function getCookieParams(): array { return []; }
    public function withCookieParams(array $cookies): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function getQueryParams(): array { return []; }
    public function withQueryParams(array $query): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function getUploadedFiles(): array { return []; }
    public function withUploadedFiles(array $uploadedFiles): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function getParsedBody() { return $this->parsedBody; }
    public function withParsedBody($data): \Psr\Http\Message\ServerRequestInterface
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }
    public function getAttributes(): array { return $this->attributes; }
    public function getAttribute(string $name, $default = null) { return $this->attributes[$name] ?? $default; }
    public function withAttribute(string $name, $value): \Psr\Http\Message\ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }
    public function withoutAttribute(string $name): \Psr\Http\Message\ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }
    public function getRequestTarget(): string { return '/'; }
    public function withRequestTarget(string $requestTarget): \Psr\Http\Message\RequestInterface { return $this; }
    public function getMethod(): string { return 'POST'; }
    public function withMethod(string $method): \Psr\Http\Message\RequestInterface { return $this; }
    public function getUri(): \Psr\Http\Message\UriInterface { return new StubUri(); }
    public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): \Psr\Http\Message\RequestInterface { return $this; }
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): \Psr\Http\Message\MessageInterface { return $this; }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
    public function getHeader(string $name): array { return (array) ($this->headers[$name] ?? []); }
    public function getHeaderLine(string $name): string
    {
        $v = $this->headers[$name] ?? '';
        return is_array($v) ? implode(',', $v) : (string) $v;
    }
    public function withHeader(string $name, $value): \Psr\Http\Message\MessageInterface
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }
    public function withAddedHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this->withHeader($name, $value); }
    public function withoutHeader(string $name): \Psr\Http\Message\MessageInterface
    {
        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }
    public function getBody(): \Psr\Http\Message\StreamInterface { return $this->body; }
    public function withBody(\Psr\Http\Message\StreamInterface $body): \Psr\Http\Message\MessageInterface { return $this; }
}
