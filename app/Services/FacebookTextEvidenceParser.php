<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Generator;
use RuntimeException;
use SplFileObject;

class FacebookTextEvidenceParser
{
    private const MONTHS = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'setiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    /**
     * Stream Facebook TXT activities without loading the complete file into memory.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function parseFile(string $path): Generator
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se pudo leer el texto temporal para analizar.');
        }

        $file = new SplFileObject($path, 'rb');
        $activity = null;
        $currentDate = null;
        $lineNumber = 0;

        foreach ($file as $line) {
            $lineNumber++;
            $line = rtrim((string) $line, "\r\n");

            if ($lineNumber === 1) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }

            $trimmedLine = trim($line);

            if ($date = $this->parseDateHeading($trimmedLine)) {
                if ($activity !== null) {
                    yield $this->buildEntry($activity, $currentDate);
                    $activity = null;
                }

                $currentDate = $date;

                continue;
            }

            if ($this->isSectionHeading($trimmedLine)) {
                if ($activity !== null) {
                    yield $this->buildEntry($activity, $currentDate);
                    $activity = null;
                }

                continue;
            }

            if ($trimmedLine !== '' && $this->isActivityStart($trimmedLine)) {
                if ($activity !== null) {
                    yield $this->buildEntry($activity, $currentDate);
                }

                $activity = [
                    'line' => $lineNumber,
                    'action' => $trimmedLine,
                    'lines' => [$trimmedLine],
                ];

                continue;
            }

            if ($activity !== null) {
                $activity['lines'][] = $line;
            }
        }

        if ($activity !== null) {
            yield $this->buildEntry($activity, $currentDate);
        }
    }

    /** @return array<string, mixed> */
    private function buildEntry(array $activity, ?array $date): array
    {
        $lines = $activity['lines'];
        $time = null;

        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        if ($lines !== []) {
            $time = $this->parseTime((string) end($lines));

            if ($time !== null) {
                array_pop($lines);
            }
        }

        $visibility = [];

        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        while ($lines !== [] && $this->isVisibility((string) end($lines))) {
            $visibility[] = trim((string) array_pop($lines));

            while ($lines !== [] && trim((string) end($lines)) === '') {
                array_pop($lines);
            }
        }

        $visibility = array_values(array_unique(array_reverse($visibility)));

        while (count($lines) > 1 && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        $type = $this->activityType($activity['action']);
        $visibilityText = implode(', ', $visibility);

        if ($type === 'reaction') {
            $dedupeText = self::extractReactionSummary((string) ($lines[0] ?? $activity['action']));
            $text = $dedupeText;
        } else {
            $dedupeText = implode("\n", $lines);
            $text = $dedupeText;

            if ($visibilityText !== '') {
                $text .= "\nVisibilidad: {$visibilityText}";
            }
        }

        if ($type === null) {
            $reason = 'El tipo de actividad no está contemplado por el importador.';
        } elseif ($date === null) {
            $reason = 'No se encontró una fecha asociada a la actividad.';
        } elseif ($time === null) {
            $reason = 'No se encontró una hora válida asociada a la actividad.';
        } else {
            $occurredAt = CarbonImmutable::createFromFormat(
                '!Y-n-j g:i a',
                sprintf('%d-%d-%d %s', $date['year'], $date['month'], $date['day'], $time),
                config('app.timezone')
            );

            if ($occurredAt === false) {
                $reason = 'La fecha o la hora no tienen un formato válido.';
            } else {
                return [
                    'kind' => 'evidence',
                    'type_key' => $type,
                    'type_label' => match ($type) {
                        'reaction' => 'Reacción',
                        'comment' => 'Comentario',
                        'share' => 'Compartido',
                    },
                    'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                    'text' => $text,
                    'dedupe_text' => $dedupeText,
                    'visibility' => $visibilityText,
                    'source_line' => $activity['line'],
                ];
            }
        }

        return [
            'kind' => 'unparsed',
            'reason' => $reason,
            'text' => $text,
            'source_line' => $activity['line'],
        ];
    }

    public static function extractReactionSummary(string $text): string
    {
        $firstLine = trim((string) strtok($text, "\r\n"));

        if (preg_match('/^(.+?\b(?:le\s+(?:gusta|gust[oó]|encanta|divierte|entristece|enoja|asombra|importa)|(?:ha\s+)?reaccion(?:ó|o|ado))\b[^.]*\.)/iu', $firstLine, $matches) === 1) {
            return trim($matches[1]);
        }

        $dotPosition = mb_strpos($firstLine, '.');
        if ($dotPosition !== false) {
            return trim(mb_substr($firstLine, 0, $dotPosition + 1));
        }

        return $firstLine;
    }

    private function isActivityStart(string $line): bool
    {
        $name = "(?:A\\s+)?[\\p{Lu}][\\p{L}\\p{M}'’.-]+(?:\\s+[\\p{Lu}][\\p{L}\\p{M}'’.-]+){1,7}";

        return preg_match('/^'.$name.'\\s+(?:le\\s+(?:gusta|gust[oó]|encanta|divierte|entristece|enoja|asombra|importa)|(?:ha\\s+)?reaccion(?:ó|o|ado)|(?:ha\\s+)?coment(?:ó|o|ado)|(?:ha\\s+)?compart(?:ió|io|o|ido))\\b/iu', $line) === 1
            || preg_match('/^'.$name.'\\s+(?:ha\\s+[\\p{L}]+|fue\\s+[\\p{L}]+|[\\p{L}\\p{M}]+(?:ó|ió))\\b/u', $line) === 1;
    }

    private function activityType(string $action): ?string
    {
        if (preg_match('/\\b(?:le\\s+(?:gusta|gust[oó]|encanta|divierte|entristece|enoja|asombra|importa)|(?:ha\\s+)?reaccion(?:ó|o|ado))\\b/iu', $action) === 1) {
            return 'reaction';
        }

        if (preg_match('/\\b(?:ha\\s+)?coment(?:ó|o|ado)\\b/iu', $action) === 1) {
            return 'comment';
        }

        if (preg_match('/\\b(?:ha\\s+)?compart(?:ió|io|o|ido)\\b/iu', $action) === 1) {
            return 'share';
        }

        return null;
    }

    /** @return array{day:int, month:int, year:int}|null */
    private function parseDateHeading(string $line): ?array
    {
        if (preg_match('/^(\d{1,2})\s+de\s+([\p{L}]+)\s+de\s+(\d{4})$/iu', $line, $matches) !== 1) {
            return null;
        }

        $month = self::MONTHS[mb_strtolower($matches[2])] ?? null;

        return $month === null ? null : [
            'day' => (int) $matches[1],
            'month' => $month,
            'year' => (int) $matches[3],
        ];
    }

    private function parseTime(string $line): ?string
    {
        $line = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', trim($line)) ?? trim($line);

        if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/iu', $line, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            if ($hour < 1 || $hour > 12 || $minute < 0 || $minute > 59) {
                return null;
            }

            return sprintf('%d:%02d %s', $hour, $minute, strtolower($matches[3]));
        }

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/u', $line, $matches) === 1) {
            return $this->convert24HourTo12Hour((int) $matches[1], (int) $matches[2]);
        }

        return null;
    }

    private function convert24HourTo12Hour(int $hour24, int $minute): string
    {
        $period = $hour24 >= 12 ? 'pm' : 'am';
        $hour12 = $hour24 % 12;

        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf('%d:%02d %s', $hour12, $minute, $period);
    }

    private function isSectionHeading(string $line): bool
    {
        $heading = mb_strtolower(trim($line, "* \t\n\r\0\x0B"));

        return str_contains($heading, 'comentarios y reacciones') || $heading === 'publicaciones';
    }

    private function isVisibility(string $line): bool
    {
        return preg_match('/^(?:público|grupo\s+(?:público|privado)|amigos(?:\s+(?:excepto|concretos).*)?|personalizado|solo\s+yo|seguidores)$/iu', trim($line)) === 1;
    }
}
