<?php

namespace Drupal\awake\Model;

class RecalculateProduct {

  public $gtin;

  public $description;

  public $price;

  public $quantity;

  public $status;

  public function __construct($data) {
    $this->gtin = $data['gtin'];
    $this->description = $data['description'];
    $this->price = $data['price'];
    $this->quantity = $data['quantity'];
    $this->status = $data['status'];
  }

}
