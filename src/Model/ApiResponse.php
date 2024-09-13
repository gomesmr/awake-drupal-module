<?php

namespace Drupal\awake\Model;

class ApiResponse {
  public $products;
  public $company;
  public $user;
  public $dateTime;

  public function __construct($products, $company, $user, $dateTime) {
    $this->products = $products;
    $this->company = $company;
    $this->user = $user;
    $this->dateTime = $dateTime;
  }
}
