<?php

namespace Drupal\awake\Controller;

use Drupal\awake\Client\AwakeClient;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeController extends ControllerBase {

  protected $awakeClient;

  public function __construct(AwakeClient $awake_client) {
    $this->awakeClient = $awake_client;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('awake.client'));
  }

  /**
   * Decide qual template ou form exibir com base no conteúdo do array
   * 'recalculateProducts'.
   */
  public function decideResponse() {
    // Recupera os dados da sessão
    $response_data = \Drupal::request()
      ->getSession()
      ->get('awake_response_data');

    // Verifique se a resposta foi recebida
    if (!$response_data) {
      return [
        '#markup' => $this->t('Nenhuma resposta disponível.'),
      ];
    }

    // Se 'recalculateProducts' estiver vazio ou não existir, renderiza o template awake_response
    if (empty($response_data['recalculateProducts'])) {
      return $this->renderResponse($response_data);
    }

    // Caso contrário, redireciona para o formulário AwakeMLevaRecalculateForm
    return $this->renderRecalculateForm($response_data);
  }

  /**
   * Renderiza o template awake_response.
   */
  protected function renderResponse(array $response_data) {
    // Verifique se há erros e armazene-os corretamente
    $errors = isset($response_data['errorMessage']) && is_array($response_data['errorMessage'])
      ? $response_data['errorMessage']
      : [];

    // Certifique-se de que $errors seja sempre um array
    if (!is_array($errors)) {
      $errors = [$errors]; // Converte para array se for uma string
    }

    // Preparação do build para renderização no Twig
    $build = [
      '#theme' => 'awake_response',
      '#products' => $response_data['products'] ?? [],
      '#errors' => $errors,
      '#company' => $response_data['company'] ?? NULL,
      '#user' => $response_data['user'] ?? NULL,
      '#dateTime' => $response_data['dateTime'] ?? NULL,
      '#recalculateProducts' => $response_data['recalculateProducts'] ?? [],
    ];

    // Desativa o cache
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Redireciona para o formulário AwakeMLevaRecalculateForm.
   */
  protected function renderRecalculateForm(array $response_data) {
    // Imprime o response para depuração
    //    echo '<pre>';
    //    print_r($response_data);
    //    echo '</pre>';
    //    exit; // Comente isso após a depuração.
    // Armazena os dados da resposta na sessão
    \Drupal::request()
      ->getSession()
      ->set('awake_response_data', $response_data);

    // Redireciona para a rota que exibe o formulário de recalculação
    return $this->redirect('awake.recalculate_form');
  }

}
