<?php

namespace Upanupstudios\Zappyride\Php\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class Zappyride
{
  private $config;
  private $httpClient;

  private $url = 'https://api.amp.active.com/anet-systemapi-ca-sec';
  private $version = 'v1';

  public function __construct(Config $config, ClientInterface $httpClient)
  {
    $this->config = $config;
    $this->httpClient = $httpClient;
  }

  public function getConfig(): Config
  {
    return $this->config;
  }

  public function getHttpClient(): ClientInterface
  {
    return $this->httpClient;
  }

  /**
   * ACTIVE Net System REST API calls are limited to 2 calls per second.
   * If the call rate exceeds 2 calls per second, then the server will return an HTTP 403 status code.
   */
  public function request(string $method, string $uri, array $options = [])
  {
    $response = [];

    try {
      $url = $this->url.'/'.$this->getConfig()->getOrganizationId().'/api/'.$this->version.'/'.$uri;

      $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ];

      if(!empty($options['headers']) && is_array($options['headers'])) {
        $headers = array_merge($headers, $options['headers']);
      }

      $query = [
        'api_key' => $this->getConfig()->getApiKey(),
        'sig' => hash('sha256', $this->getConfig()->getApiKey().$this->getConfig()->getSecret().time())
      ];

      if(!empty($options['query']) && is_array($options['query'])) {
        $query = array_merge($query, $options['query']);
      }

      // Start microtime, return in seconds
      $mtime_start = microtime(true);

      $request = $this->httpClient->request($method, $url, [
        'headers' => $headers,
        'query' => $query
      ]);

      // Get body
      $body = $request->getBody();

      // Decode and return as array
      $response = json_decode($body->__toString(), TRUE);

      // End microtime, return in seconds
      $mtime_end = microtime(true);

      // Calulate difference in seconds
      $diff_mtime = $mtime_end - $mtime_start;

      if($diff_mtime < 0.5) {
        // Sleep until after 0.5 seconds
        $mtime = (0.5 - $diff_mtime) * 1000000;

        // Add time to make sure it's past the 0.5 seconds
        $mtime += 100000;

        usleep($mtime);
      }
    } catch (RequestException $exception) {
      $response = $exception->getMessage();
    }

    return $response;
  }

  /**
   * @return object
   *
   * @throws InvalidArgumentException
   */
  public function api(string $name)
  {
    $api = null;

    switch ($name) {
      case 'General':
        $api = new General($this);
        break;

      case 'Activity':
        $api = new Activity($this);
        break;

      default:
        throw new \InvalidArgumentException();
    }

    return $api;
  }

  public function __call(string $name, array $args): object
  {
    try {
      return $this->api($name);
    } catch (\InvalidArgumentException $e) {
      throw new \BadMethodCallException("Undefined method called: '$name'.");
    }
  }
}