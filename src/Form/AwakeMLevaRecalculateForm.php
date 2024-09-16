<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use GuzzleHttp\Client;

class AwakeMLevaRecalculateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'awake_mleva_recalculate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Recupera os produtos recalculáveis da sessão.
    $response_data = Drupal::request()
      ->getSession()
      ->get('awake_response_data');

    // Recupera os arrays 'recalculateProducts' e 'products'.
    $recalculateProducts = $response_data['recalculateProducts'] ?? [];
    $products = $response_data['products'] ?? [];
    $company = $response_data['company'] ?? [];
    $user = $response_data['user'] ?? [];

    // Une os dois arrays.
    $combinedProducts = array_merge($products, $recalculateProducts);

    // Se não houver produtos, exibe uma mensagem de erro.
    if (empty($combinedProducts)) {
      $this->messenger()->addError($this->t('No products to recalculate.'));
      return [];
    }

    // Adicionar campos de informações da empresa
    $form['company_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Informações da Empresa'),
    ];
    $form['company_info']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome da Empresa'),
      '#default_value' => $company['companyName'] ?? '',
      '#required' => TRUE,
      '#attributes' => ['readonly' => 'readonly'],
      '#id' => 'edit-company-name',
    ];
    $form['company_info']['localization_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Localização da Empresa'),
      '#default_value' => $company['localization'] ?? '',
      '#required' => TRUE,
      '#id' => 'edit-company-localization',
    ];

    // Adicionar campos de informações do usuário
    $form['user_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Informações do Usuário'),
    ];
    $form['user_info']['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Usuário'),
      '#default_value' => $user['userName'] ?? '',
      '#required' => TRUE,
      '#attributes' => ['readonly' => 'readonly'],
      '#id' => 'edit-user-name',
    ];

    // Adicionar produtos
    $form['products'] = [
      '#type' => 'container',
    ];

    // Itera sobre os produtos para criar o formulário.
    foreach ($combinedProducts as $index => $product) {
      $form['products'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto @number', ['@number' => $index + 1]),
      ];

      // Gerar os campos dinamicamente.
      $form['products'][$index]['gtin'] = $this->buildProductField($index, 'GTIN', $product['gtin'], FALSE, TRUE);
      $form['products'][$index]['description'] = $this->buildProductField($index, 'Description', $product['description'], FALSE);
      $form['products'][$index]['price'] = $this->buildProductField($index, 'Price', $product['price'], TRUE, TRUE);
      $form['products'][$index]['volume'] = $this->buildProductField($index, 'Volume', $product['volume']);
      $form['products'][$index]['quantity'] = $this->buildProductField($index, 'Quantity', $product['quantity']);
      $form['products'][$index]['unity'] = $this->buildProductField($index, 'Unity', $product['unity']);
      $form['products'][$index]['status'] = $this->buildProductField($index, 'Status', $product['status'], FALSE);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Recalculate'),
    ];

    return $form;
  }

  /**
   * Função auxiliar para criar campos do produto.
   */
  private function buildProductField($index, $title, $default_value, $editable = TRUE, $required = FALSE): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t($title),
      '#default_value' => $default_value,
      '#required' => $required,
      '#attributes' => $editable ? [] : ['readonly' => 'readonly'],
      '#id' => "edit-$title-$index",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Coleta os dados do formulário.
    $products = $form_state->getValue('products');
    $company_name = $form_state->getValue('company_name');
    $localization = $form_state->getValue('localization');
    $user_name = $form_state->getValue('user_name');

    // Verifica se a variável $products é um array.
    if (!is_array($products)) {
      $this->messenger()->addError($this->t('No products data found.'));
      return;
    }

    $payload = [
      'products' => array_values($products),
      'company' => [
        'companyName' => $company_name,
        'localization' => $localization,
      ],
      'user' => [
        'userName' => $user_name,
      ],
    ];

    // Log do payload antes de enviar a requisição.
    Drupal::logger('awake')
      ->info('Payload enviado: <pre>@payload</pre>', ['@payload' => print_r($payload, TRUE)]);

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
        Drupal::request()
          ->getSession()
          ->set('awake_response_data', $response_data);

        // Redireciona para a página de resposta.
        $form_state->setRedirect('awake.response_page');
      }
      else {
        $this->messenger()
          ->addError($this->t('Failed to recalculate. Status code: @code', ['@code' => $status_code]));
      }
    }
    catch (Exception $e) {
      $this->messenger()
        ->addError($this->t('Error during recalculation: @message', ['@message' => $e->getMessage()]));
    }
  }

}
