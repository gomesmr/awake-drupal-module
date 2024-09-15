<?php

namespace Drupal\awake\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use GuzzleHttp\Client;

class AwakePostForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'awake_post_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Primeiro conjunto de campos para GTIN 01 e Preço 01
    $form['group1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('GTIN 01 e Preço 01'),
    ];

    $form['group1']['field_gtin_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GTIN 01'),
      '#default_value' => '7896045506910',
      '#required' => TRUE,
    ];

    $form['group1']['field_preco_1'] = [
      '#type' => 'number',
      '#title' => $this->t('Preço 01'),
      '#default_value' => '37',
      '#required' => TRUE,
    ];

    // Segundo conjunto de campos para GTIN 02 e Preço 02
    $form['group2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('GTIN 02 e Preço 02'),
    ];

    $form['group2']['field_gtin_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GTIN 02'),
      '#default_value' => '7898955352168',
      '#required' => TRUE,
    ];

    $form['group2']['field_preco_2'] = [
      '#type' => 'number',
      '#title' => $this->t('Preço 02'),
      '#default_value' => '23',
      '#required' => TRUE,
    ];

    // Campos para informações da empresa
    $form['company'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Informações da Empresa'),
    ];

    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome da Empresa'),
      '#default_value' => 'Varejão',
      '#required' => TRUE,
    ];

    $form['company']['company_localization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Localização da Empresa'),
      '#default_value' => '-22.83512749482224, -45.2265350010767',
      '#required' => TRUE,
    ];

    // Campo para o nome do usuário
    $form['user'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Informações do Usuário'),
    ];

    $form['user']['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Usuário'),
      '#default_value' => 'lobsom',
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Pegue os valores do formulário
    $gtin1 = $form_state->getValue('field_gtin_1');
    $price1 = $form_state->getValue('field_preco_1');
    $gtin2 = $form_state->getValue('field_gtin_2');
    $price2 = $form_state->getValue('field_preco_2');
    $companyName = $form_state->getValue('company_name');
    $companyLocalization = $form_state->getValue('company_localization');
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
        'localization' => $companyLocalization,
      ],
      'user' => [
        'userName' => $userName,
      ],
    ];

    // Faça a requisição POST usando Guzzle
    $client = new Client();
    try {
      $response = $client->post('http://mleva-api:8080/mleva', [
        'json' => $payload,
      ]);

      // Verifica a resposta
      $status_code = $response->getStatusCode();
      $response_body = $response->getBody()->getContents();

      if ($status_code == 200) {
        // Armazena a resposta na sessão para ser recuperada depois
        $response_data = json_decode($response_body, TRUE);
        Drupal::messenger()->addMessage('Dados enviados com sucesso!');
        Drupal::request()
          ->getSession()
          ->set('awake_response_data', $response_data);

        // Redireciona para a página de exibição
        $form_state->setRedirect('awake.response_page');
      }
      else {
        $this->messenger()
          ->addError($this->t('Erro ao enviar os dados. Status code: @code', ['@code' => $status_code]));
      }
    }
    catch (Exception $e) {
      $this->messenger()
        ->addError($this->t('Erro ao conectar com o serviço: @message', ['@message' => $e->getMessage()]));
    }
  }

}
