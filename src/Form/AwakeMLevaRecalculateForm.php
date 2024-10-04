<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\awake\Client\AwakeClient;
use Drupal\awake\Helper\AwakeResponseHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method t(string $string)
 */
class AwakeMLevaRecalculateForm extends FormBase {

  protected $awakeClient;

  protected $responseHelper;

  /**
   * Construtor da classe, injetando os serviços AwakeClient e
   * AwakeResponseHelper.
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
    // Adiciona a biblioteca de estilos do módulo
    $form['#attached']['library'][] = 'awake/styles';
    // Adiciona a biblioteca de máscara de preço
    $form['#attached']['library'][] = 'awake/js';

    // Recupera os produtos recalculáveis e em análise da sessão.
    $response_data = Drupal::request()
      ->getSession()
      ->get('awake_response_data');
    Drupal::logger('awake')
      ->info('Dados da sessão recuperados: @response_data', ['@response_data' => json_encode($response_data)]);

    // Recupera os arrays 'products' e 'products_recalculate'.
    $products = $response_data['products'] ?? [];
    Drupal::logger('awake')
      ->info('Produtos em análise recuperados: @products', ['@products' => json_encode($products)]);

    $products_recalculate = $response_data['recalculateProducts'] ?? [];
    Drupal::logger('awake')
      ->info('Produtos para recalculação recuperados: @products_recalculate', ['@products_recalculate' => json_encode($products_recalculate)]);

    $company = $response_data['company'] ?? [];

    $user = $response_data['user'] ?? [];

    if (empty($products) && empty($products_recalculate)) {
      $this->messenger()
        ->addError($this->t('No products to recalculate or analyze.'));
      return [];
    }

    $form['company_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dados do Comércio'),
    ];

    $form['company_info']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onde estou comprando'),
      '#default_value' => $company['companyName'] ?? '',
      '#required' => TRUE,
      '#attributes' => ['readonly' => 'readonly'],
      '#id' => 'edit-company-name',
    ];

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

    $form['products'] = [
      '#type' => 'container',
      '#parents' => ['products'],
      '#title' => $this->t('Produtos em Análise'),
    ];

    foreach ($products as $index => $product) {
      $form['products'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto @number', ['@number' => $index + 1]),
      ];

      $form['products'][$index]['gtin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Código de Barras'),
        '#default_value' => $product['gtin'],
        '#description' => $this->t('Código de barras do produto.'),
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products', $index, 'gtin'],
      ];

      $form['products'][$index]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Descrição'),
        '#default_value' => $product['description'],
        '#description' => $this->t('Descrição detalhada do produto.'),
        '#attributes' => ['readonly' => 'readonly'],
        '#required' => FALSE,
        '#parents' => ['products', $index, 'description'],
      ];

      $form['products'][$index]['price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Preço'),
        '#default_value' => $product['price'],
        '#description' => $this->t('Preço do produto.'),
        '#attributes' => ['readonly' => 'readonly'],
        '#required' => TRUE,
        '#parents' => ['products', $index, 'price'],
      ];

      $form['products'][$index]['volume'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Volume'),
        '#default_value' => $product['volume'],
        '#description' => $this->t('Volume do produto.'),
        '#required' => TRUE,
        '#parents' => ['products', $index, 'volume'],
      ];

      $form['products'][$index]['quantity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Quantidade'),
        '#default_value' => $product['quantity'],
        '#description' => $this->t('Quantidade do produto.'),
        '#required' => TRUE,
        '#parents' => ['products', $index, 'quantity'],
      ];

      $form['products'][$index]['unity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unidade'),
        '#default_value' => $product['unity'],
        '#description' => $this->t('Unidade de medida do produto.'),
        '#required' => TRUE,
        '#parents' => ['products', $index, 'unity'],
      ];

      $form['products'][$index]['status'] = [
        '#type' => 'hidden',
        '#default_value' => $product['status'],
        '#parents' => ['products', $index, 'status'],
      ];
    }

    $form['products_recalculate'] = [
      '#type' => 'container',
      '#parents' => ['products_recalculate'],
      '#title' => $this->t('Produtos que precisam de sua ajuda :)'),
      '#description' => $this->t('Os produtos abaixo têm dados que precisam ser completados.'),

    ];

    foreach ($products_recalculate as $index => $product) {
      $form['products_recalculate'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto @number', ['@number' => $index + 1]),
      ];

      $form['products_recalculate'][$index]['gtin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Código de Barras'),
        '#default_value' => $product['gtin'],
        '#description' => $this->t('Código de barras do produto.'),
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products_recalculate', $index, 'gtin'],
      ];

      $form['products_recalculate'][$index]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Descrição'),
        '#default_value' => $product['description'],
        '#description' => $this->t('Descrição do produto.'),
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products_recalculate', $index, 'description'],
      ];

      $form['products_recalculate'][$index]['price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Preço'),
        '#default_value' => $product['price'],
        '#description' => $this->t('Preço do produto.'),
        '#required' => TRUE,
        '#attributes' => ['readonly' => 'readonly'],
        '#parents' => ['products_recalculate', $index, 'price'],
      ];

      $form['products_recalculate'][$index]['volume'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Volume'),
        '#default_value' => $product['volume'],
        '#description' => $this->t('Volume do produto.'),
        '#required' => TRUE,
        '#parents' => ['products_recalculate', $index, 'volume'],
      ];

      $form['products_recalculate'][$index]['quantity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Quantidade'),
        '#default_value' => $product['quantity'],
        '#description' => $this->t('Número de produtos contidos nessa unidade de venda.'),
        '#required' => TRUE,
        '#parents' => ['products_recalculate', $index, 'quantity'],
      ];

      $form['products_recalculate'][$index]['unity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unidade'),
        '#default_value' => $product['unity'],
        '#description' => $this->t('Unidade de medida do produto. Unidades de volume: L , ML. Unidades de comprimento: M, CM. Unidades de massa: G, KG, MG. Unidade de quantidade: U, DOSE.'),
        '#required' => TRUE,
        '#parents' => ['products_recalculate', $index, 'unity'],
      ];

      $form['products_recalculate'][$index]['status'] = [
        '#type' => 'hidden',
        '#default_value' => $product['status'],
        '#parents' => ['products_recalculate', $index, 'status'],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Recalculate'),
    ];

    Drupal::logger('awake')
      ->info('Formulario: @form', ['@form' => json_encode($form)]);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Coleta os dados do formulário.
    $products = $form_state->getValue('products') ?? [];
    Drupal::logger('awake')
      ->info('Produtos recebidos: @products', ['@products' => json_encode($products)]);

    $products_recalculate = $form_state->getValue('products_recalculate') ?? [];
    Drupal::logger('awake')
      ->info('Produtos recalcular: @products_recalculate', ['@products_recalculate' => json_encode($products_recalculate)]);

    $company_name = $form_state->getValue('company_name');
    Drupal::logger('awake')
      ->info('Nome da empresa recebido: @company_name', ['@company_name' => $company_name]);

    $user_name = $form_state->getValue('user_name');
    Drupal::logger('awake')
      ->info('Nome do usuário recebido: @user_name', ['@user_name' => $user_name]);

    $date_time = date('Y-m-d H:i:s');  // Pega a data e hora atual
    Drupal::logger('awake')
      ->info('Data e Hora: @data_hora', ['@data_hora' => $date_time]);

    if (!is_array($products)) {
      $this->messenger()->addError($this->t('No valid products data found.'));
      return;
    }

    if (!is_array($products_recalculate)) {
      $this->messenger()->addError($this->t('No products to recalculate.'));
      return;
    }

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
    Drupal::logger('awake')
      ->info('Payload enviado: @payload', ['@payload' => json_encode($payload)]);

    // Faça a requisição POST usando Guzzle
    $client = new Client();
    try {
      $response = $client->post('https://app.mleva.com.br/mleva/recalculate', [
        'json' => $payload,
      ]);

      // Verifica a resposta usando a classe auxiliar
      $this->responseHelper->processResponse($response, $form_state);
    }
    catch (Exception $e) {
      $this->messenger()
        ->addError($this->t('Erro ao conectar com o serviço: @message', ['@message' => $e->getMessage()]));
    }
  }

}
