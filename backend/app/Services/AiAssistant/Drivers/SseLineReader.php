<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Incremental Server-Sent Events parser over a PSR stream. Events are
 * separated by a blank line and may be split at any byte between chunks.
 */
final class SseLineReader
{
    private const CHUNK_BYTES = 8192;

    /**
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public static function events(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(self::CHUNK_BYTES);
            if ($chunk === '') {
                continue;
            }

            $buffer .= str_replace("\r\n", "\n", $chunk);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = self::parseBlock(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 2);
                if ($event !== null) {
                    yield $event;
                }
            }
        }

        $tail = self::parseBlock($buffer);
        if ($tail !== null) {
            yield $tail;
        }
    }

    /**
     * @return array{event: ?string, data: string}|null
     */
    private static function parseBlock(string $block): ?array
    {
        $event = null;
        $data = [];

        foreach (explode("\n", $block) as $line) {
            if ($line === '' || $line[0] === ':') {
                continue;
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = ltrim($value, ' ');

            if ($field === 'event') {
                $event = $value;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }

        return $data === [] ? null : ['event' => $event, 'data' => implode("\n", $data)];
    }
}
