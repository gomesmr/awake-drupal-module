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
    // Recupera os dados da sessão
    $response_data = \Drupal::request()->getSession()->get('awake_response_data');

    // Verifique se a resposta foi recebida
    if (!$response_data) {
      return [
        '#markup' => $this->t('Nenhuma resposta disponível.'),
      ];
    }

    // Verifique se há erros e armazene-os corretamente
    $errors = isset($response_data['errorMessage']) && is_array($response_data['errorMessage'])
      ? $response_data['errorMessage']
      : [];

    // Certifique-se de que $errors seja sempre um array
    if (!is_array($errors)) {
      $errors = [$errors]; // Converte para array se for uma string
    }
    // Imprime o response para depuração
//    echo '<pre>';
//    print_r($response_data);
//    echo '</pre>';
//    exit; // Comente isso após a depuração.

    // Preparação do build para renderização no Twig
    $build = [
      '#theme' => 'awake_response',
      '#products' => $response_data['products'] ?? [],
      '#errors' => $errors,
      '#company' => $response_data['company'] ?? null,
      '#user' => $response_data['user'] ?? null,
      '#dateTime' => $response_data['dateTime'] ?? null,
      '#recalculateProducts' => $response_data['recalculateProducts'] ?? [],
    ];

    // Desativa o cache
    $build['#cache']['max-age'] = 0;

    return $build;
  }
}
