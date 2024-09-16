<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\awake\Client\AwakeClient;
use Drupal\awake\Helper\AwakeResponseHelper;
use GuzzleHttp\Client;

class AwakeMLevaRecalculateForm extends FormBase {

  protected $awakeClient;
  protected $responseHelper;

  /**
   * Construtor da classe, injetando os serviços AwakeClient e AwakeResponseHelper.
   */
  public function __construct(AwakeClient $awake_client, AwakeResponseHelper $response_helper) {
    $this->awakeClient = $awake_client;
    $this->responseHelper = $response_helper;
  }

  /**
   * Cria a instância da classe e injeta os serviços via container.
   */
  public static function create(ContainerInterface $container): AwakeMLevaRecalculateForm|static {
    return new static(
      $container->get('awake.client'),
      $container->get('awake.response_helper')  // Injeta o helper de resposta
    );
  }

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
    // Recupera os produtos recalculáveis e em análise da sessão.
    $response_data = Drupal::request()->getSession()->get('awake_response_data');
    Drupal::logger('awake')->info('Dados da sessão recuperados: @response_data', ['@response_data' => json_encode($response_data)]);

    // Recupera os arrays 'products' e 'products_recalculate'.
    $products = $response_data['products'] ?? [];
    Drupal::logger('awake')->info('Produtos em análise recuperados: @products', ['@products' => json_encode($products)]);

    $products_recalculate = $response_data['recalculateProducts'] ?? [];
    Drupal::logger('awake')->info('Produtos para recalculação recuperados: @products_recalculate', ['@products_recalculate' => json_encode($products_recalculate)]);

    $company = $response_data['company'] ?? [];

    $user = $response_data['user'] ?? [];

    // Se não houver produtos, exibe uma mensagem de erro.
    if (empty($products) && empty($products_recalculate)) {
      $this->messenger()->addError($this->t('No products to recalculate or analyze.'));
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

    // Adicionar produtos em análise
    $form['products'] = [
      '#type' => 'container',
      '#parents' => ['products'],  // Define os parents para os dados do array
      '#title' => $this->t('Produtos em Análise'),
    ];

    foreach ($products as $index => $product) {
      $form['products'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto @number', ['@number' => $index + 1]),
      ];

      $form['products'][$index]['gtin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('GTIN'),
        '#default_value' => $product['gtin'],
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products', $index, 'gtin'],  // Corrige o aninhamento
      ];

      $form['products'][$index]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Descrição'),
        '#default_value' => $product['description'],
        '#required' => FALSE,
        '#parents' => ['products', $index, 'description'],  // Corrige o aninhamento
      ];

      $form['products'][$index]['price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Preço'),
        '#default_value' => $product['price'],
        '#required' => TRUE,
        '#parents' => ['products', $index, 'price'],  // Corrige o aninhamento
      ];

      // Continue para os outros campos (volume, quantity, unity, status)
      $form['products'][$index]['volume'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Volume'),
        '#default_value' => $product['volume'],
        '#required' => FALSE,
        '#parents' => ['products', $index, 'volume'],
      ];

      $form['products'][$index]['quantity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Quantidade'),
        '#default_value' => $product['quantity'],
        '#required' => FALSE,
        '#parents' => ['products', $index, 'quantity'],
      ];

      $form['products'][$index]['unity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unidade'),
        '#default_value' => $product['unity'],
        '#required' => FALSE,
        '#parents' => ['products', $index, 'unity'],
      ];

      $form['products'][$index]['status'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => $product['status'],
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products', $index, 'status'],
      ];
    }

    // Adicionar produtos para recalculação
    $form['products_recalculate'] = [
      '#type' => 'container',
      '#parents' => ['products_recalculate'],  // Define os parents para os dados do array
      '#title' => $this->t('Produtos para Recalcular'),
    ];

    foreach ($products_recalculate as $index => $product) {
      $form['products_recalculate'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto Recalcular @number', ['@number' => $index + 1]),
      ];

      $form['products_recalculate'][$index]['gtin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('GTIN'),
        '#default_value' => $product['gtin'],
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products_recalculate', $index, 'gtin'],  // Corrige o aninhamento
      ];

      $form['products_recalculate'][$index]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Descrição'),
        '#default_value' => $product['description'],
        '#required' => FALSE,
        '#parents' => ['products_recalculate', $index, 'description'],  // Corrige o aninhamento
      ];

      $form['products_recalculate'][$index]['price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Preço'),
        '#default_value' => $product['price'],
        '#required' => TRUE,
        '#parents' => ['products_recalculate', $index, 'price'],  // Corrige o aninhamento
      ];

      // Continue para os outros campos (volume, quantity, unity, status)
      $form['products_recalculate'][$index]['volume'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Volume'),
        '#default_value' => $product['volume'],
        '#required' => FALSE,
        '#parents' => ['products_recalculate', $index, 'volume'],
      ];

      $form['products_recalculate'][$index]['quantity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Quantidade'),
        '#default_value' => $product['quantity'],
        '#required' => FALSE,
        '#parents' => ['products_recalculate', $index, 'quantity'],
      ];

      $form['products_recalculate'][$index]['unity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unidade'),
        '#default_value' => $product['unity'],
        '#required' => FALSE,
        '#parents' => ['products_recalculate', $index, 'unity'],
      ];

      $form['products_recalculate'][$index]['status'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => $product['status'],
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products_recalculate', $index, 'status'],
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Coleta os dados do formulário.
    $products = $form_state->getValue('products');
    Drupal::logger('awake')->info('Produtos recebidos: @products', ['@products' => json_encode($products)]);

    $products_recalculate = $form_state->getValue('products_recalculate');
    Drupal::logger('awake')->info('Produtos recalcular: @products_recalculate', ['@products_recalculate' => json_encode($products_recalculate)]);

    $company_name = $form_state->getValue('company_name');
    Drupal::logger('awake')->info('Nome da empresa recebido: @company_name', ['@company_name' => $company_name]);

    $user_name = $form_state->getValue('user_name');
    Drupal::logger('awake')->info('Nome do usuário recebido: @user_name', ['@user_name' => $user_name]);

    $date_time = date('Y-m-d H:i:s');  // Pega a data e hora atual
    Drupal::logger('awake')->info('Data e Hora: @data_hora', ['@data_hora' => $date_time]);

    // Verifica se a variável $products e $products_recalculate são arrays.
    if (!is_array($products)) {
      $this->messenger()->addError($this->t('No products data found.'));
      return;
    }

    if (!is_array($products_recalculate)) {
      $this->messenger()->addError($this->t('No products to recalculate.'));
      return;
    }

    // Monta o payload.
    $payload = [
      'products' => array_values($products),
      'products_recalculate' => array_values($products_recalculate),
      'company' => [
        'companyName' => $company_name,
      ],
      'user' => [
        'userName' => $user_name,
      ],
      'date_time' => $date_time,
    ];

    // Log do payload antes de enviar a requisição.
    Drupal::logger('awake')->info('Payload enviado: @payload', ['@payload' => json_encode($payload)]);

    // Faça a requisição POST usando Guzzle
    $client = new Client();
    try {
      $response = $client->post('http://mleva-api:8080/mleva/recalculate', [
        'json' => $payload,
      ]);

      // Verifica a resposta usando a classe auxiliar
      $this->responseHelper->verificaAResposta($response, $form_state);
    }
    catch (Exception $e) {
      $this->messenger()->addError($this->t('Erro ao conectar com o serviço: @message', ['@message' => $e->getMessage()]));
    }
  }
}
