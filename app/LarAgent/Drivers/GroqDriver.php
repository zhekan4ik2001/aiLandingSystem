<?php

namespace App\LarAgent\Drivers;

use App\Integrations\Groq\GroqClient;
use LarAgent\Core\DTO\DriverConfig;
use LarAgent\Drivers\Groq\GroqDriver as BaseGroqDriver;

class GroqDriver extends BaseGroqDriver
{
    public function __construct(DriverConfig|array $settings = [])
    {
        parent::__construct($settings);

        $driverConfig = $this->getDriverConfig();
        $apiKey = $driverConfig->apiKey;
        $apiUrl = $driverConfig->apiUrl;

        if (! $apiKey) {
            $this->client = null;

            return;
        }

        $options = [];
        if ($apiUrl) {
            $options['baseUrl'] = rtrim($apiUrl, '/');
        }

        $this->client = new GroqClient($apiKey, $options);
    }
}
