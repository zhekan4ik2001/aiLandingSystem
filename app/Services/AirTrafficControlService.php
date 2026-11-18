<?php



namespace App\Services;



use App\AiAgents\AirTrafficControlAgent;

use App\Models\Airplane;

use App\Support\GeoCalculator;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Log;



class AirTrafficControlService

{

    private const AGENT_RATE_LIMIT_KEY = 'atc.agent.last_run';



    public function __construct(

        private readonly AircraftProximityService $aircraftProximityService,

        private readonly GroqAvailabilityService $groqAvailabilityService,

        private readonly AircraftCommandJournal $commandJournal,

        private readonly SimulationTimeService $simulationTimeService,

        private readonly AtcTextCommandParser $commandParser,

        private readonly AtcTextCommandExecutor $commandExecutor,

    ) {}



    /**

     * Run AI traffic control to prevent proximity conflicts.

     *

     * @return array<string, mixed>

     */

    public function avoidCollisions(): array

    {

        if (! $this->simulationTimeService->isRunning()) {

            return $this->skipped('simulation_paused', true);

        }



        if (! config('air-traffic-control.enabled', true)) {

            return $this->skipped('disabled', false);

        }



        if (empty(config('air-traffic-control.groq_api_key'))) {

            return $this->skipped('missing_api_key', false);

        }



        if (! $this->groqAvailabilityService->isAvailable()) {

            return $this->skipped('groq_unavailable', false);

        }



        $intervalSeconds = max(1, (int) config('air-traffic-control.agent_interval_seconds', 10));

        if ($this->isAgentRateLimited($intervalSeconds)) {

            return $this->skipped('rate_limited', true);

        }



        $airplanes = Airplane::query()

            ->where('is_active', true)

            ->orderBy('id')

            ->get();



        if ($airplanes->count() < 2) {

            return $this->skipped('not_enough_airplanes', true);

        }



        $conflictsBefore = $this->aircraftProximityService->detectConflicts();

        $pairs = $this->buildPairDistances($airplanes);

        $warningKm = (float) config('air-traffic-control.warning_distance_km', 5.0);

        $needsIntervention = $conflictsBefore !== []

            || $this->hasPairCloserThan($pairs, $warningKm);



        if (! $needsIntervention) {

            return [

                'ran' => false,

                'reason' => 'safe_separation',

                'groq_available' => true,

                'conflicts_before' => count($conflictsBefore),

                'conflicts_after' => count($conflictsBefore),

                'commands' => [],

                'commands_count' => 0,

            ];

        }



        $payload = $airplanes

            ->map(static fn (Airplane $airplane) => [

                'id' => $airplane->id,

                'latitude' => round((float) $airplane->latitude, 6),

                'longitude' => round((float) $airplane->longitude, 6),

                'altitude_feet' => round((float) $airplane->altitude_feet, 1),

                'direction_deg' => round((float) $airplane->direction, 1),

                'speed_kmh' => round((float) $airplane->speed, 1),

            ])

            ->values()

            ->all();



        $agentMessage = null;

        $parsedCommands = [];

        $this->commandJournal->begin();

        $agentTimeout = (int) config('air-traffic-control.agent_timeout', 12);

        $previousLimit = ini_get('max_execution_time');

        set_time_limit(max(1, $agentTimeout + 2));



        try {

            $agent = AirTrafficControlAgent::make()

                ->temperature((float) config('air-traffic-control.temperature', 0.2))

                ->maxCompletionTokens((int) config('air-traffic-control.max_completion_tokens', 1024));



            $agentMessage = (string) $agent->respond(json_encode($payload, JSON_UNESCAPED_UNICODE));

            $parsedCommands = $this->commandParser->parse($agentMessage);



            if ($parsedCommands !== []) {

                $this->commandExecutor->execute($parsedCommands);

            }



            Cache::put(self::AGENT_RATE_LIMIT_KEY, now()->timestamp, $intervalSeconds + 5);

        } catch (\Throwable $exception) {

            $connectivityFailure = $this->isGroqConnectivityFailure($exception);



            Log::warning('Air traffic AI control failed', [

                'message' => $exception->getMessage(),

                'type' => $exception::class,

                'previous' => $exception->getPrevious()?->getMessage(),

                'connectivity' => $connectivityFailure,

            ]);



            if ($connectivityFailure) {

                $this->groqAvailabilityService->markUnavailable();

            }



            $commands = $this->commandJournal->pull();



            return [

                'ran' => false,

                'reason' => 'agent_error',

                'groq_available' => ! $connectivityFailure,

                'error_message' => $exception->getMessage(),

                'conflicts_before' => count($conflictsBefore),

                'conflicts_after' => count($this->aircraftProximityService->detectConflicts()),

                'commands' => $commands,

                'commands_count' => count($commands),

                'agent_message' => $agentMessage,

                'parsed_commands' => $parsedCommands,

            ];

        } finally {

            if ($previousLimit !== false && $previousLimit !== '') {

                set_time_limit((int) $previousLimit);

            }

        }



        $conflictsAfter = $this->aircraftProximityService->detectConflicts();

        $commands = $this->commandJournal->pull();

        $commandsCount = count($commands);



        return [

            'ran' => true,

            'groq_available' => true,

            'conflicts_before' => count($conflictsBefore),

            'conflicts_after' => count($conflictsAfter),

            'agent_message' => $agentMessage,

            'parsed_commands' => $parsedCommands,

            'commands_parsed_count' => count($parsedCommands),

            'commands' => $commands,

            'commands_count' => $commandsCount,

            'commands_applied' => $commandsCount > 0,

            'parse_warning' => $commandsCount === 0 && $parsedCommands !== []

                ? 'Команды распознаны, но не применены'

                : ($commandsCount === 0 && $this->responseLooksLikeCommands($agentMessage)

                    ? 'Ответ агента не соответствует формату «id команда значение»'

                    : null),

        ];

    }



    /**

     * @return array<string, mixed>

     */

    private function skipped(string $reason, bool $groqAvailable): array

    {

        return [

            'ran' => false,

            'reason' => $reason,

            'groq_available' => $groqAvailable,

            'conflicts_before' => 0,

            'conflicts_after' => 0,

            'commands' => [],

            'commands_count' => 0,

        ];

    }



    private function isAgentRateLimited(int $intervalSeconds): bool

    {

        $lastRun = Cache::get(self::AGENT_RATE_LIMIT_KEY);

        if ($lastRun === null) {

            return false;

        }



        return (now()->timestamp - (int) $lastRun) < $intervalSeconds;

    }



    private function responseLooksLikeCommands(?string $message): bool

    {

        if ($message === null || trim($message) === '') {

            return false;

        }



        return (bool) preg_match('/\b(increase|decrease|speed|altitude|climb|descend)\b/i', $message)

            || (bool) preg_match('/\d+\s+[a-z_]+\s+[\d.]+/i', $message);

    }



    /**

     * @param  \Illuminate\Support\Collection<int, Airplane>  $airplanes

     * @return list<array{airplane_ids: array{int, int}, distance_km: float}>

     */

    private function buildPairDistances($airplanes): array

    {

        $pairs = [];

        $minVerticalFeet = (float) config('air-traffic-control.min_vertical_separation_feet', 1000);



        for ($i = 0; $i < $airplanes->count(); $i++) {

            for ($j = $i + 1; $j < $airplanes->count(); $j++) {

                $first = $airplanes[$i];

                $second = $airplanes[$j];



                if (abs((float) $first->altitude_feet - (float) $second->altitude_feet) >= $minVerticalFeet) {

                    continue;

                }



                $pairs[] = [

                    'airplane_ids' => [(int) $first->id, (int) $second->id],

                    'distance_km' => round(GeoCalculator::distanceKm(

                        (float) $first->latitude,

                        (float) $first->longitude,

                        (float) $second->latitude,

                        (float) $second->longitude

                    ), 3),

                ];

            }

        }



        return $pairs;

    }



    /**

     * @param  list<array{airplane_ids: array{int, int}, distance_km: float}>  $pairs

     */

    private function hasPairCloserThan(array $pairs, float $thresholdKm): bool

    {

        foreach ($pairs as $pair) {

            if ($pair['distance_km'] < $thresholdKm) {

                return true;

            }

        }



        return false;

    }



    private function isGroqConnectivityFailure(\Throwable $exception): bool

    {

        $messages = [$exception->getMessage()];

        $previous = $exception->getPrevious();

        if ($previous !== null) {

            $messages[] = $previous->getMessage();

        }



        $haystack = strtolower(implode(' ', $messages));



        return str_contains($haystack, 'ssl certificate')

            || str_contains($haystack, 'curl error 60')

            || str_contains($haystack, 'could not resolve host')

            || str_contains($haystack, 'connection timed out')

            || str_contains($haystack, 'failed to connect');

    }

}

