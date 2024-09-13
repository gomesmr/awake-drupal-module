<?php

namespace Drupal\awake\Model;

class Company {
  public $companyName;

  public function __construct($data) {
    $this->companyName = $data['companyName'] ?? null;
  }
}
