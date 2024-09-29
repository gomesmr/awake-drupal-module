<?php

namespace Model;

class User {

  public $userName;

  public function __construct(array $data) {
    $this->userName = $data['userName'] ?? NULL;
  }

}