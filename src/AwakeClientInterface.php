<?php

namespace Drupal\awake;

interface AwakeClientInterface {
  public function connect($method, $endpoint, $query, $body);
}
