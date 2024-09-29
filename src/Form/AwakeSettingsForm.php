<?php

namespace Drupal\awake\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @method t(string $string)
 */
class AwakeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['awake.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'awake_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('awake.settings');

    $form['custom_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom CSS'),
      '#description' => $this->t('Add custom CSS to style the display of API data.'),
      '#default_value' => $config->get('custom_css'),
      '#rows' => 10,
      '#resizable' => 'vertical',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('awake.settings')
      ->set('custom_css', $form_state->getValue('custom_css'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
