<?php

namespace Drupal\awake\Model;

class ProductGetId {

  public $gtin;

  public $description;

  public function __construct($data) {
    $this->gtin = $data['gtin'];
    $this->description = $data['description'];
  }

}
