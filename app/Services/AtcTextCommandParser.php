<?php

namespace App\Services;

class AtcTextCommandParser
{
    /** @var array<string, string> */
    private const COMMAND_ALIASES = [
        'increase_speed' => 'increase_speed',
        'decrease_speed' => 'decrease_speed',
        'increase_altitude' => 'increase_altitude',
        'decrease_altitude' => 'decrease_altitude',
        'speed_up' => 'increase_speed',
        'speed_down' => 'decrease_speed',
        'slow_down' => 'decrease_speed',
        'climb' => 'increase_altitude',
        'descend' => 'decrease_altitude',
        'altitude_up' => 'increase_altitude',
        'altitude_down' => 'decrease_altitude',
    ];

    /**
     * @return list<array{airplane_id: int, command: string, value: float, raw_line: string}>
     */
    public function parse(string $text): array
    {
        $commands = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^[-*]\s*/', '', $line) ?? $line;
            $line = trim($line, '`"\' ');

            if (preg_match('/^(none|нет|no\s*commands?|без\s*команд)/iu', $line)) {
                continue;
            }

            if (preg_match('/^(\d+)\s+([a-z_]+)\s+([\d.]+)/iu', $line, $matches) !== 1) {
                continue;
            }

            $command = $this->normalizeCommand($matches[2]);
            if ($command === null) {
                continue;
            }

            $commands[] = [
                'airplane_id' => (int) $matches[1],
                'command' => $command,
                'value' => (float) $matches[3],
                'raw_line' => $line,
            ];
        }

        return $commands;
    }

    private function normalizeCommand(string $raw): ?string
    {
        $key = strtolower(str_replace('-', '_', trim($raw)));

        return self::COMMAND_ALIASES[$key] ?? null;
    }
}
