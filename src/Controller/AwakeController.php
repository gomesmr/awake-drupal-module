<?php

namespace Drupal\awake\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\awake\Client\AwakeClient;

class AwakeController extends ControllerBase {
  protected $awakeClient;

  public function __construct(AwakeClient $awake_client) {
    $this->awakeClient = $awake_client;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('awake.client'));
  }

  public function postData() {
    $payload = [
      'products' => [['gtin' => '7896075300205', 'price' => '36.89']],
      'company' => ['companyName' => 'Company'],
      'user' => ['userName' => 'user'],
    ];

    $response = $this->awakeClient->connect('post', '/awake', [], $payload);
    return $response ? [
      '#theme' => 'awake_compare_response',
      '#products' => $response['products'],
      '#company' => $response['company'],
      '#user' => $response['user'],
      '#dateTime' => $response['dateTime'],
    ] : ['#markup' => 'Error occurred'];
  }

  public function getProductByGtin($gtin) {
    $response = $this->awakeClient->connect('get', '/awake-product/' . $gtin);
    return $response ? ['#theme' => 'awake_get_product_by_id', '#product' => $response] : ['#markup' => 'Product not found'];
  }
}
