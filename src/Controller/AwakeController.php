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

  public function renderResponse() {
    // Recupera os dados armazenados na sessão
    $response_data = \Drupal::request()->getSession()->get('awake_response_data');

    if (!$response_data) {
      return [
        '#markup' => $this->t('Nenhuma resposta disponível.'),
      ];
    }

    // Renderiza o template Twig com os dados da resposta
    return [
      '#theme' => 'awake_response',
      '#products' => $response_data['products'],
      '#company' => $response_data['company'],
      '#user' => $response_data['user'],
      '#dateTime' => $response_data['dateTime'],
    ];
  }
}
