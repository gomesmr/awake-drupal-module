<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\awake\Client\AwakeClient;
use Drupal\awake\Helper\AwakeResponseHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method t(string $string)
 */
class AwakeMLevaCompareForm extends FormBase {

  protected $awakeClient;

  protected $responseHelper;

  protected $currentUser;

  /**
   * Construtor da classe, injetando os serviços AwakeClient,
   * AwakeResponseHelper e current_user.
   */
  public function __construct(AwakeClient $awake_client, AwakeResponseHelper $response_helper, $current_user) {
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
      $container->get('awake.response_helper'),  // Injeta o helper de resposta
      $container->get('current_user')  // Injeta o serviço de usuário atual
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

    // Verifica quantos conjuntos de campos já foram adicionados
    $products = $form_state->get('products') ?? [];

    // Inicialize os dois primeiros produtos se não estiverem definidos
    if (empty($products)) {
      $products = [
        ['gtin' => '', 'price' => ''],  // Produto 1
        ['gtin' => '', 'price' => ''],  // Produto 2
      ];
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
        '#title' => $this->t("Código de Barras @num", ['@num' => $key + 1 ]),
        '#default_value' => $product['gtin'] ?? '',
        '#description' => $this->t("Informe o código de barras do produto @num que deseja comparar.", ['@num' => $key +1 ]),
        '#required' => TRUE,
      ];

      $form['products_wrapper']['product_' . $key]['field_preco_' . $key] = [
        '#type' => 'textfield',
        '#title' => $this->t("Preço @num", ['@num' => $key + 1]),
        '#default_value' => $product['price'] ?? '',
        '#description' => $this->t("Informe o preço do produto @num para a comparação.", ['@num' => $key + 1]),
        '#required' => TRUE,
      ];

      // Botão para remover o conjunto de campos
      $form['products_wrapper']['product_' . $key]['remove_product'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remover Produto'),
        '#submit' => ['::removeProduct'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'products-wrapper',
        ],
        '#name' => 'remove_product_' . $key,
        // Impede a validação ao remover um produto
        '#limit_validation_errors' => [],
      ];
    }

    // Botão para adicionar mais conjuntos de campos
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

    // Obtenha o nome do usuário atual e preencha o campo automaticamente
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
    // Adiciona um novo produto à lista
    $products = $form_state->get('products') ?? [];
    $products[] = ['gtin' => '', 'price' => ''];
    $form_state->set('products', $products);

    // Rebuild the form to reflect the new number of products
    $form_state->setRebuild();
  }

  public function removeProduct(array &$form, FormStateInterface $form_state) {
    // Obter o nome do botão que foi clicado
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // Extrair o índice do produto a ser removido
    if (preg_match('/remove_product_(\d+)/', $button_name, $matches)) {
      $index = $matches[1];

      // Impedir que os dois primeiros produtos sejam removidos
      if ($index < 2) {
        $this->messenger()
          ->addWarning($this->t('Os dois primeiros produtos não podem ser removidos.'));
        return;
      }

      // Remover o produto da lista
      $products = $form_state->get('products');
      unset($products[$index]);

      // Reindexar o array para evitar buracos
      $form_state->set('products', array_values($products));
    }

    // Rebuild the form to reflect the changes
    $form_state->setRebuild();
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['products_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $products = $form_state->get('products') ?? [];
    $companyName = $form_state->getValue('company_name');
    $localization = $form_state->getValue('localization_field');
    $userName = $form_state->getValue('user_name');

    // Monte o payload para a requisição POST
    $payload = [
      'products' => $products,
      'company' => [
        'companyName' => $companyName,
        'localization' => $localization,
      ],
      'user' => [
        'userName' => $userName,
      ],
    ];

    // Faça a requisição POST usando Guzzle
    $client = new Client();
    try {
      $response = $client->post('https://mleva-04f05d539d3b.herokuapp.com/mleva', [
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
