<?php

namespace Drupal\awake\Client;

use Drupal\awake\AwakeClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Messenger\MessengerInterface;

class AwakeClient implements AwakeClientInterface {
  protected $httpClient;
  protected $messenger;
  protected $base_uri = 'http://host.docker.internal:8081';

  public function __construct(ClientInterface $http_client, MessengerInterface $messenger) {
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
  }

  public function connect($method, $endpoint, $query = [], $body = null) {
    try {
      $options = $this->buildOptions($query, $body);
      $response = $this->httpClient->{$method}($this->base_uri . $endpoint, $options);
      return json_decode($response->getBody()->getContents(), true);
    } catch (RequestException $exception) {
      $this->messenger->addError(t('Error: %error', ['%error' => $exception->getMessage()]));
      return FALSE;
    }
  }

  private function buildOptions($query, $body) {
    $options = ['headers' => ['Content-Type' => 'application/json']];
    if ($body) {
      $options['body'] = is_array($body) ? json_encode($body) : $body;
    }
    if ($query) {
      $options['query'] = $query;
    }
    return $options;
  }
}
