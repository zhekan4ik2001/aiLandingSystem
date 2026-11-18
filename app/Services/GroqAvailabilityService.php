<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAvailabilityService
{
    private const CACHE_KEY = 'groq.api.available';

    private ?string $lastCheckError = null;

    /**
     * Whether Groq API is configured and reachable.
     */
    public function isAvailable(): bool
    {
        if (! config('air-traffic-control.enabled', true)) {
            return false;
        }

        $apiKey = (string) config('air-traffic-control.groq_api_key', '');
        if ($apiKey === '') {
            return false;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return (bool) $cached;
        }

        return $this->checkApi($apiKey);
    }

    /**
     * Mark Groq as temporarily unavailable (after failed agent call).
     */
    public function markUnavailable(): void
    {
        $ttl = (int) config('air-traffic-control.unavailable_cache_seconds', 300);
        Cache::put(self::CACHE_KEY, false, $ttl);
    }

    /**
     * Force a fresh availability check (ignores positive cache).
     */
    public function checkApi(?string $apiKey = null): bool
    {
        $apiKey ??= (string) config('air-traffic-control.groq_api_key', '');
        if ($apiKey === '') {
            return false;
        }

        $timeout = (int) config('air-traffic-control.availability_timeout', 30);
        $cacheTtl = (int) config('air-traffic-control.availability_cache_seconds', 60);

        $this->lastCheckError = null;

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->connectTimeout(min(2, $timeout))
                ->get('https://api.groq.com/openai/v1/models');

            $available = $response->successful();
        } catch (\Throwable $exception) {
            $this->lastCheckError = $exception->getMessage();
            Log::debug('Groq API availability check failed', [
                'message' => $exception->getMessage(),
            ]);
            $available = false;
        }

        Cache::put(self::CACHE_KEY, $available, $available ? $cacheTtl : min($cacheTtl, 30));

        return $available;
    }

    /**
     * Detailed Groq status for UI and browser console.
     *
     * @return array{available: bool, enabled: bool, has_api_key: bool, reason: string, message: string, from_cache: bool}
     */
    public function getStatusReport(): array
    {
        $enabled = (bool) config('air-traffic-control.enabled', true);
        $hasApiKey = ! empty(config('air-traffic-control.groq_api_key'));

        if (! $enabled) {
            return [
                'available' => false,
                'enabled' => false,
                'has_api_key' => $hasApiKey,
                'reason' => 'disabled',
                'message' => 'AI-контроль отключён в настройках (AIR_TRAFFIC_AI_ENABLED=false)',
                'from_cache' => false,
            ];
        }

        if (! $hasApiKey) {
            return [
                'available' => false,
                'enabled' => true,
                'has_api_key' => false,
                'reason' => 'missing_api_key',
                'message' => 'Groq API ключ не задан (GROQ_API_KEY)',
                'from_cache' => false,
            ];
        }

        $fromCache = Cache::has(self::CACHE_KEY);
        $available = $this->checkApi();
        $reason = $available ? 'ok' : $this->resolveUnavailableReason();

        return [
            'available' => $available,
            'enabled' => true,
            'has_api_key' => true,
            'reason' => $reason,
            'message' => $available
                ? 'Groq API доступен — AI-агент может управлять самолётами'
                : $this->unavailableMessage($reason),
            'from_cache' => $fromCache,
        ];
    }

    private function resolveUnavailableReason(): string
    {
        $error = $this->lastCheckError ?? '';
        if (str_contains($error, 'SSL certificate problem') || str_contains($error, 'cURL error 60')) {
            return 'ssl_certificate';
        }

        return 'unreachable';
    }

    private function unavailableMessage(string $reason): string
    {
        return match ($reason) {
            'ssl_certificate' => 'Groq API недоступен из PHP: нет CA-сертификатов (curl.cainfo / openssl.cafile в php.ini или bootstrap/certs/cacert.pem)',
            default => 'Groq API недоступен — симуляция работает без AI-контроля',
        };
    }
}
