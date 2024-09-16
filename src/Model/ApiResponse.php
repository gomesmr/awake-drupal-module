<?php
namespace Drupal\awake\Model;

class ApiResponse {
  public $products;
  public $errors;
  public $company;
  public $user;
  public $dateTime;
  public $recalculateProducts;

  public function __construct($products, $errors, $company, $user, $dateTime, $recalculateProducts) {
    $this->products = $products;
    $this->errors = $errors;
    $this->company = $company;
    $this->user = $user;
    $this->dateTime = $dateTime;
    $this->recalculateProducts = $recalculateProducts;
  }
}
