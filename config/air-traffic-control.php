<?php

return [

  'enabled' => env('AIR_TRAFFIC_AI_ENABLED', true),

  'groq_api_key' => env('GROQ_API_KEY'),

  'warning_distance_km' => (float) env('AIR_TRAFFIC_WARNING_KM', 5),

  'temperature' => (float) env('AIR_TRAFFIC_AI_TEMPERATURE', 0.2),

  'max_completion_tokens' => (int) env('AIR_TRAFFIC_AI_MAX_TOKENS', 1024),

  'availability_timeout' => (int) env('AIR_TRAFFIC_GROQ_CHECK_TIMEOUT', 3),

  'availability_cache_seconds' => (int) env('AIR_TRAFFIC_GROQ_CACHE_SECONDS', 60),

  'unavailable_cache_seconds' => (int) env('AIR_TRAFFIC_GROQ_UNAVAILABLE_CACHE', 300),

  'agent_timeout' => (int) env('AIR_TRAFFIC_AGENT_TIMEOUT', 12),

  'agent_interval_seconds' => (int) env('AIR_TRAFFIC_AGENT_INTERVAL', 10),

  'proximity_check_interval_seconds' => (int) env('AIR_TRAFFIC_PROXIMITY_INTERVAL', 1),

  'min_vertical_separation_feet' => (float) env('AIR_TRAFFIC_MIN_VERTICAL_SEPARATION_FT', 1000),

];
