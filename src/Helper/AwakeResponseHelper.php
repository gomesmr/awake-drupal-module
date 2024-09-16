<?php

namespace Drupal\awake\Helper;

use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Psr7\Response;
use Exception;

class AwakeResponseHelper {

  /**
   * Verifica a resposta da requisição e age de acordo.
   *
   * @param Response $response
   * @param FormStateInterface $form_state
   *
   * @return void
   */
  public function verificaAResposta(Response $response, FormStateInterface $form_state): void {
    $status_code = $response->getStatusCode();
    $response_body = $response->getBody()->getContents();

    if ($status_code == 200) {
      // Armazena a resposta na sessão para ser recuperada depois
      $response_data = json_decode($response_body, TRUE);
      \Drupal::messenger()->addMessage('Dados enviados com sucesso!');
      \Drupal::request()->getSession()->set('awake_response_data', $response_data);

      // Redireciona para a página de exibição
      $form_state->setRedirect('awake.response_page');
    }
    else {
      \Drupal::messenger()->addError(t('Erro ao enviar os dados. Status code: @code', ['@code' => $status_code]));
    }
  }
}
