<?php

namespace Model;

class ErrorMessage {

  public $errorMessage;

  public function __construct($data) {
    $this->errorMessage = $data;
  }

}
