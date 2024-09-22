<?php

namespace Drupal\awake\Model;

class Company {

  public mixed $companyName;

  public mixed $localization;

  public function __construct($data) {
    $this->companyName = $data['companyName'] ?? NULL;
    $this->localization = $data['localization'] ?? NULL;
  }

}
