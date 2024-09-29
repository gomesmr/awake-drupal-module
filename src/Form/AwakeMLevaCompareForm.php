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
    $num_products = $form_state->get('num_products');
    if ($num_products === NULL) {
      $num_products = 2; // Começa com 2 conjuntos de campos
      $form_state->set('num_products', $num_products);
    }

    // Loop para adicionar os conjuntos de campos dinamicamente
    for ($i = 1; $i <= $num_products; $i++) {
      $form["group{$i}"] = [
        '#type' => 'fieldset',
        '#title' => $this->t("Cód Barras {$i} e Preço {$i}"),
      ];

      $form["group{$i}"]["field_gtin_{$i}"] = [
        '#type' => 'textfield',
        '#title' => $this->t("Código de Barras {$i}"),
        '#default_value' => '',
        '#description' => $this->t("Informe o código de barras do produto {$i} que deseja comparar."),
        '#required' => TRUE,
      ];

      $form["group{$i}"]["field_preco_{$i}"] = [
        '#type' => 'textfield',
        '#title' => $this->t("Preço {$i}"),
        '#default_value' => '',
        '#description' => $this->t("Informe o preço do produto {$i} para a comparação."),
        '#required' => TRUE,
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

    // Wrapper para os campos de produtos
    $form['#prefix'] = '<div id="products-wrapper">';
    $form['#suffix'] = '</div>';

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
    // Incrementa o número de conjuntos de campos
    $num_products = $form_state->get('num_products');
    $form_state->set('num_products', $num_products + 1);

    // Rebuild the form to reflect the new number of products
    $form_state->setRebuild();
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $num_products = $form_state->get('num_products');
    $products = [];

    for ($i = 1; $i <= $num_products; $i++) {
      $gtin = $form_state->getValue("field_gtin_{$i}");
      $price = $form_state->getValue("field_preco_{$i}");
      $products[] = [
        'gtin' => $gtin,
        'price' => $price,
      ];
    }

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
