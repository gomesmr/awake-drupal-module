<?php

namespace Drupal\awake\Model;

class Company {
  public mixed $companyName;
  public mixed $localization;

  public function __construct($data) {
    $this->companyName = $data['companyName'] ?? null;
    $this->localization = $data['localization'] ?? null;
  }
}
