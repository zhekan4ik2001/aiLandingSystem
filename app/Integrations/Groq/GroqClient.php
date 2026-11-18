<?php

namespace App\Integrations\Groq;

use App\Support\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use LucianoTonet\GroqPHP\Groq;
use Psr\Http\Message\ResponseInterface;

/**
 * Groq HTTP client with CA bundle support for Windows/WAMP (groq-php uses Guzzle without verify).
 */
class GroqClient extends Groq
{
    public function makeRequest(Request $request): ResponseInterface
    {
        $config = [
            'base_uri' => $this->baseUrl(),
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey(),
            ],
        ];

        $verify = CaBundle::httpVerifyOption();
        if ($verify !== true) {
            $config['verify'] = $verify;
        }

        return (new Client($config))->send($request);
    }
}
