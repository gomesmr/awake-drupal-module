<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use Exception;

class AwakeMLevaRecalculateForm extends FormBase {

  public function getFormId() {
    return 'awake_mleva_recalculate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Recupera os produtos recalculáveis da sessão.
    $response_data = \Drupal::request()->getSession()->get('awake_response_data');

    // Recupera os arrays 'recalculateProducts' e 'products'.
    $recalculateProducts = $response_data['recalculateProducts'] ?? [];
    $products = $response_data['products'] ?? [];

    // Une os dois arrays.
    $combinedProducts = array_merge($products, $recalculateProducts);

    // Se não houver produtos, exibe uma mensagem de erro.
    if (empty($combinedProducts)) {
      $this->messenger()->addError($this->t('No products to recalculate.'));
      return [];
    }

    $form['products'] = [
      '#type' => 'container',
    ];

    // Itera sobre os produtos para criar o formulário.
    foreach ($combinedProducts as $index => $product) {
      $form['products'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Product @number', ['@number' => $index + 1]),
      ];

      // Campo GTIN, não editável.
      $form['products'][$index]['gtin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('GTIN'),
        '#default_value' => $product['gtin'],
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#name' => "products[$index][gtin]", // Definindo um nome correto.
      ];

      // Campo Descrição, não editável.
      $form['products'][$index]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#default_value' => $product['description'],
        '#attributes' => ['readonly' => 'readonly'],
        '#name' => "products[$index][description]", // Definindo um nome correto.
      ];

      // Campo Preço, pode ser editado.
      $form['products'][$index]['price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Price'),
        '#default_value' => $product['price'],
        '#required' => TRUE,
        '#name' => "products[$index][price]", // Definindo um nome correto.
      ];

      // Campo Volume, pode ser editado.
      $form['products'][$index]['volume'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Volume'),
        '#default_value' => $product['volume'] ?? '',
        '#name' => "products[$index][volume]", // Definindo um nome correto.
      ];

      // Campo Quantidade, pode ser editado.
      $form['products'][$index]['quantity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Quantity'),
        '#default_value' => $product['quantity'] ?? '',
        '#name' => "products[$index][quantity]", // Definindo um nome correto.
      ];

      // Campo Unidade, pode ser editado.
      $form['products'][$index]['unity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unity'),
        '#default_value' => $product['unity'] ?? '',
        '#name' => "products[$index][unity]", // Definindo um nome correto.
      ];

      // Campo Status, não editável.
      $form['products'][$index]['status'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => $product['status'],
        '#attributes' => ['readonly' => 'readonly'],
        '#name' => "products[$index][status]", // Definindo um nome correto.
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Recalculate'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Coleta os dados do formulário.
    $products = $form_state->getValue('products');

    // Verifica se a variável $products é um array.
    if (!is_array($products)) {
      $this->messenger()->addError($this->t('No products data found.'));
      return;
    }

    $payload = ['products' => array_values($products)];

    // Log do payload antes de enviar a requisição.
    \Drupal::logger('awake')->info('Payload enviado: <pre>@payload</pre>', ['@payload' => print_r($payload, TRUE)]);

    // Faz a requisição POST usando Guzzle.
    $client = new Client();
    try {
      $response = $client->post('http://mleva-api:8080/mleva/recalculate', ['json' => $payload]);

      // Verifica a resposta.
      $status_code = $response->getStatusCode();
      $response_body = $response->getBody()->getContents();

      if ($status_code == 200) {
        // Armazena a nova resposta na sessão.
        $response_data = json_decode($response_body, TRUE);
        Drupal::messenger()->addMessage('Recalculation successful.');
        \Drupal::request()->getSession()->set('awake_response_data', $response_data);

        // Redireciona para a página de resposta.
        $form_state->setRedirect('awake.response_page');
      } else {
        $this->messenger()->addError($this->t('Failed to recalculate. Status code: @code', ['@code' => $status_code]));
      }
    } catch (Exception $e) {
      $this->messenger()->addError($this->t('Error during recalculation: @message', ['@message' => $e->getMessage()]));
    }
  }

}
