<?php

namespace App\AiAgents;

use LarAgent\Agent;

class AirTrafficControlAgent extends Agent
{
    protected $model = 'llama-3.3-70b-versatile';

    protected $history = 'in_memory';

    protected $provider = 'groq';

    protected $temperature = 0.2;

    protected $maxCompletionTokens = 1024;

    protected $tools = [];

    public function instructions(): string
    {
        $interval = (int) config('air-traffic-control.agent_interval_seconds', 10);

        return <<<PROMPT
You are an air traffic control AI near Pulkovo airport.

IMPORTANT — control interval: you are invoked at most once every {$interval} seconds. After you answer, you cannot change anything for the next {$interval} seconds. Plan decisive corrections now; aircraft will keep moving during that gap.

INPUT: JSON array of airplanes. Each item has only:
- id
- latitude (degrees)
- longitude (degrees)
- altitude_feet
- direction_deg (0–360)
- speed_kmh

OUTPUT — plain text only, no markdown, no tools, no explanations unless needed.
One command per line, exactly:
{id} {command} {value}

Allowed commands:
- increase_speed — value in km/h to add
- decrease_speed — value in km/h to subtract
- increase_altitude — value in feet to climb
- decrease_altitude — value in feet to descend

Examples:
12 decrease_speed 50
7 increase_altitude 1000

If no change is needed, output a single line: NONE

Rules:
- Keep horizontal separation at least 1 km; use vertical separation when useful.
- Only use airplane ids from the input.
- Issue every command you want applied in this window; there is no second chance for {$interval} seconds.
PROMPT;
    }

    public function prompt($message): string
    {
        return $message;
    }
}
