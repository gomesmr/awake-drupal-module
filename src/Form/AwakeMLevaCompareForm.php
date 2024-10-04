<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\awake\Client\AwakeClient;
use Drupal\awake\Helper\AwakeResponseHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LogLevel;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Formulário para comparação de produtos MLeva.
 */
class AwakeMLevaCompareForm extends FormBase {

  protected $awakeClient;

  protected $responseHelper;

  protected $currentUser;

  /**
   * Construtor da classe, injetando os serviços AwakeClient,
   * AwakeResponseHelper e current_user.
   */
  public function __construct(AwakeClient $awake_client, AwakeResponseHelper $response_helper, AccountProxyInterface $current_user) {
    $this->awakeClient = $awake_client;
    $this->responseHelper = $response_helper;
    $this->currentUser = $current_user;
  }

  /**
   * Cria a instância da classe e injeta os serviços via container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('awake.client'),
      $container->get('awake.response_helper'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'awake_mleva_compare_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'awake/styles';
    $form['#attached']['library'][] = 'awake/js';

    $products = $form_state->get('products') ?? [];

    // Inicializa os dois primeiros produtos, se ainda não estiverem configurados
    if (empty($products)) {
      $products = [
        ['gtin' => '', 'price' => ''],  // Produto 1
        ['gtin' => '', 'price' => ''],  // Produto 2
      ];
      $form_state->set('products', $products); // Salva no estado
    }

    // Wrapper para os produtos
    $form['products_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'products-wrapper'],
    ];

    // Loop para adicionar os conjuntos de campos dinamicamente
    foreach ($products as $key => $product) {
      $form['products_wrapper']['product_' . $key] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Produto @num', ['@num' => $key + 1]),
      ];

      $form['products_wrapper']['product_' . $key]['field_gtin_' . $key] = [
        '#type' => 'textfield',
        '#title' => $this->t("Código de Barras @num", ['@num' => $key + 1]),
        '#default_value' => $product['gtin'] ?? '',
        '#description' => $this->t("Informe o código de barras do produto @num que deseja comparar.", ['@num' => $key + 1]),
        '#required' => TRUE,
      ];

      $form['products_wrapper']['product_' . $key]['field_preco_' . $key] = [
        '#type' => 'textfield',
        '#title' => $this->t("Preço @num", ['@num' => $key + 1]),
        '#default_value' => $product['price'] ?? '',
        '#description' => $this->t("Informe o preço do produto @num para a comparação.", ['@num' => $key + 1]),
        '#required' => TRUE,
      ];

      // Exibir o botão "Remover Produto" apenas para produtos após os dois primeiros
      if ($key >= 2) {
        $form['products_wrapper']['product_' . $key]['remove_product'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remover Produto'),
          '#submit' => ['::removeProduct'],
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'products-wrapper',
          ],
          '#name' => 'remove_product_' . $key,
          '#limit_validation_errors' => [],
        ];
      }
    }

    // Botão para adicionar mais produtos
    $form['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Adicionar mais produtos'),
      '#submit' => ['::addMoreProducts'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'products-wrapper',
      ],
    ];

    // Campos para informações da empresa
    $form['company'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dados do Comércio'),
    ];

    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onde estou comprando'),
      '#default_value' => '',
      '#description' => $this->t('Informe o nome do comércio onde está realizando a compra.'),
      '#required' => FALSE,
    ];

    $form['company']['localization_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Estou em...'),
      '#default_value' => '',
      '#description' => $this->t('Informe a localização do comércio, caso seja relevante.'),
      '#required' => FALSE,
    ];

    // Campo para o nome do usuário (preenchido automaticamente)
    $form['user'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Informações do Usuário'),
    ];

    $currentUserName = $this->currentUser->getDisplayName();
    $form['user']['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Usuário'),
      '#default_value' => $currentUserName,
      '#description' => $this->t('Nome do usuário autenticado no sistema.'),
      '#required' => TRUE,
    ];

    // Botão de envio do formulário
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function addMoreProducts(array &$form, FormStateInterface $form_state) {
    // Recupera os valores preenchidos dos produtos existentes
    $values = $form_state->getValues();
    $products = $form_state->get('products');

    // Atualiza os produtos já existentes com os valores atuais
    foreach ($products as $key => &$product) {
      $product['gtin'] = $values['field_gtin_' . $key] ?? '';
      $product['price'] = $values['field_preco_' . $key] ?? '';
    }

    // Adiciona um novo produto à lista
    $products[] = ['gtin' => '', 'price' => ''];
    $form_state->set('products', $products);

    $form_state->setRebuild();
  }

  public function removeProduct(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if (preg_match('/remove_product_(\d+)/', $button_name, $matches)) {
      $index = $matches[1];

      if ($index < 2) {
        $this->messenger()->addWarning($this->t('Os dois primeiros produtos não podem ser removidos.'));
        return;
      }

      $values = $form_state->getValues();
      $products = $form_state->get('products');

      foreach ($products as $key => &$product) {
        $product['gtin'] = $values['field_gtin_' . $key] ?? '';
        $product['price'] = $values['field_preco_' . $key] ?? '';
      }

      unset($products[$index]);
      $form_state->set('products', array_values($products));
    }

    $form_state->setRebuild();
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['products_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Captura os valores mais recentes dos campos dinâmicos
    $products = [];
    $values = $form_state->getValues();
    $stored_products = $form_state->get('products');

    foreach ($stored_products as $key => $product) {
      $products[] = [
        'gtin' => $values['field_gtin_' . $key] ?? '',
        'price' => $values['field_preco_' . $key] ?? '',
      ];
    }

    $company_name = $form_state->getValue('company_name');
    $localization = $form_state->getValue('localization_field');
    $user_name = $form_state->getValue('user_name');

    // Cria o payload para enviar à API com os nomes corretos das chaves
    $payload = [
      'products' => $products,
      'company' => [
        'companyName' => $company_name,
        'localization' => $localization,
      ],
      'user' => [
        'userName' => $user_name,
      ],
    ];

    // Log do payload
    Drupal::logger('awake')->log(LogLevel::INFO, 'Payload enviado: <pre>@payload</pre>', [
      '@payload' => json_encode($payload, JSON_PRETTY_PRINT),
    ]);

    // Faça a requisição POST usando Guzzle
    $client = new Client();
    try {
      $response = $client->post('https://app.mleva.com.br/mleva', [
        'json' => $payload,
      ]);

      // Verifica a resposta usando a classe auxiliar
      $this->responseHelper->processResponse($response, $form_state);
    } catch (\Exception $e) {
      Drupal::logger('awake')->error('Erro ao conectar com o serviço: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Erro ao conectar com o serviço: @message', ['@message' => $e->getMessage()]));
    }
  }
}
