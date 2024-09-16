<?php

namespace Drupal\awake\Model;

class Company {
  public $companyName;
  public $localization;

  public function __construct($data) {
    $this->companyName = $data['companyName'] ?? null;
    $this->localization = $data['localization'] ?? null;
  }
}
