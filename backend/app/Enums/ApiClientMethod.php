<?php

namespace App\Enums;

/**
 * The HTTP methods an API client may be allowed to use (spec 0392). PUT also
 * governs PATCH (both update); HEAD and OPTIONS, like a read-only POST, count
 * as GET.
 */
enum ApiClientMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';

    public static function forRequest(string $httpMethod, bool $readOnly): self
    {
        return match (strtoupper($httpMethod)) {
            'POST' => $readOnly ? self::Get : self::Post,
            'PUT', 'PATCH' => self::Put,
            'DELETE' => self::Delete,
            default => self::Get,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $method): string => $method->value, self::cases());
    }
}
