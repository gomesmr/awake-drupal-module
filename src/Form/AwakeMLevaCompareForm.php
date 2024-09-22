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
    // Adiciona a biblioteca de estilos do módulo
    $form['#attached']['library'][] = 'awake/styles';

    // Primeiro conjunto de campos para GTIN 01 e Preço 01
    $form['group1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cód Barras 01 e Preço 01'),
    ];

    $form['group1']['field_gtin_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Código de Barras 01'),
      '#default_value' => '',
      '#description' => $this->t('Informe o código de barras do primeiro produto que deseja comparar.'),
      '#required' => TRUE,
    ];

    $form['group1']['field_preco_1'] = [
      '#type' => 'number',
      '#step' => '0.01',
      '#title' => $this->t('Preço 01'),
      '#default_value' => '',
      '#description' => $this->t('Informe o preço do primeiro produto para a comparação.'),
      '#required' => TRUE,
    ];

    // Segundo conjunto de campos para GTIN 02 e Preço 02
    $form['group2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cód Barras 02 e Preço 02'),
    ];

    $form['group2']['field_gtin_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Código de Barras 02'),
      '#default_value' => '',
      '#description' => $this->t('Informe o código de barras do segundo produto que deseja comparar.'),
      '#required' => TRUE,
    ];

    $form['group2']['field_preco_2'] = [
      '#type' => 'number',
      '#step' => '0.01',
      '#title' => $this->t('Preço 02'),
      '#default_value' => '',
      '#description' => $this->t('Informe o preço do segundo produto para a comparação.'),
      '#required' => TRUE,
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Pegue os valores do formulário
    $gtin1 = $form_state->getValue('field_gtin_1');
    $price1 = $form_state->getValue('field_preco_1');
    $gtin2 = $form_state->getValue('field_gtin_2');
    $price2 = $form_state->getValue('field_preco_2');
    $companyName = $form_state->getValue('company_name');
    $localization = $form_state->getValue('localization_field');
    $userName = $form_state->getValue('user_name');

    // Monte o payload para a requisição POST
    $payload = [
      'products' => [
        [
          'gtin' => $gtin1,
          'price' => $price1,
        ],
        [
          'gtin' => $gtin2,
          'price' => $price2,
        ],
      ],
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
