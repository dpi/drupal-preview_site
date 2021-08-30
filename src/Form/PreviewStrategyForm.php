<?php

namespace Drupal\preview_site\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for adding/editing PreviewStrategy entities.
 *
 * @codeCoverageIgnore
 * @see \Drupal\Tests\preview_site\Functional\PreviewStrategyAdministrationTest
 */
class PreviewStrategyForm extends EntityForm {

  /**
   * Plugin manager.
   *
   * @var \Drupal\preview_site\Generate\GeneratePluginManager
   */
  protected $generatePluginManager;

  /**
   * Plugin manager.
   *
   * @var \Drupal\preview_site\Deploy\DeployPluginManager
   */
  protected $deployPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->deployPluginManager = $container->get('plugin.manager.preview_site_deploy');
    $instance->generatePluginManager = $container->get('plugin.manager.preview_site_generate');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\preview_site\Entity\PreviewStrategyInterface $strategy */
    $strategy = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $strategy->label(),
      '#description' => $this->t("Label for the Preview strategy."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $strategy->id(),
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\preview_site\Entity\PreviewStrategy::load',
      ],
      '#disabled' => !$strategy->isNew(),
    ];

    $generate_ajax = [
      'callback' => [static::class, 'updateGenerateForm'],
      'wrapper' => 'plugin-settings-generate',
      'effect' => 'fade',
      'method' => 'replaceWith',
    ];
    $generate_plugin_id = $form_state->getValue('generate', $strategy->getGeneratePluginId() ?: NULL);
    $generate_plugin = $strategy->getGeneratePlugin() ?: NULL;
    if ($generate_plugin_id && !$generate_plugin) {
      $generate_plugin = $this->generatePluginManager->createInstance($generate_plugin_id);
    }
    $form['generate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Generation method'),
      '#description' => $this->t('Select the approach to use to generate preview items for this strategy.'),
      '#default_value' => $generate_plugin_id,
      '#required' => TRUE,
      '#options' => array_map(function (array $plugin) {
        return $plugin['title'];
      }, $this->generatePluginManager->getDefinitions()),
      '#ajax' => $generate_ajax + [
        'trigger_as' => ['name' => 'op', 'value' => 'Update generate plugin'],
      ],
    ];
    $form['update_generate_plugin'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update generate plugin'),
      '#limit_validation_errors' => [['generate']],
      '#attributes' => [
        'class' => ['visually-hidden'],
      ],
      '#submit' => ['::rebuildForm'],
      '#ajax' => $generate_ajax,
    ];
    $form['generateSettings'] = [
      '#prefix' => '<div id="plugin-settings-generate">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => new TranslatableMarkup('Generation method settings'),
      '__none' => [
        '#markup' => new TranslatableMarkup('There are no settings for the selected option.'),
      ],
    ];
    if ($generate_plugin && $generate_plugin->hasFormClass('configure')) {

      $subform_state = SubformState::createForSubform($form['generateSettings'], $form, $form_state);
      $settings_form = $generate_plugin->buildConfigurationForm($form['generateSettings'], $subform_state);
      if ($settings_form) {
        $form['generateSettings'] += $settings_form;
        unset($form['generateSettings']['__none']);
      }
    }

    $deploy_ajax = [
      'callback' => [static::class, 'updateDeployForm'],
      'wrapper' => 'plugin-settings-deploy',
      'effect' => 'fade',
      'method' => 'replaceWith',
    ];
    $deploy_plugin_id = $form_state->getValue('deploy', $strategy->getDeployPluginId() ?: NULL);
    $deploy_plugin = $strategy->getDeployPlugin() ?: NULL;
    if ($deploy_plugin_id && !$deploy_plugin) {
      $deploy_plugin = $this->deployPluginManager->createInstance($deploy_plugin_id);
    }
    $form['deploy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Deployment method'),
      '#description' => $this->t('Select the approach to use to deploy generated previews for this strategy.'),
      '#default_value' => $deploy_plugin_id,
      '#required' => TRUE,
      '#options' => array_map(function (array $plugin) {
        return $plugin['title'];
      }, $this->deployPluginManager->getDefinitions()),
      '#ajax' => $deploy_ajax + [
        'trigger_as' => ['name' => 'op', 'value' => 'Update deploy plugin'],
      ],
    ];
    $form['update_deploy_plugin'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update deploy plugin'),
      '#limit_validation_errors' => [['deploy']],
      '#attributes' => [
        'class' => ['visually-hidden'],
      ],
      '#submit' => ['::rebuildForm'],
      '#ajax' => $deploy_ajax,
    ];
    $form['deploySettings'] = [
      '#prefix' => '<div id="plugin-settings-deploy">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => new TranslatableMarkup('Deployment method settings'),
      '__none' => [
        '#markup' => new TranslatableMarkup('There are no settings for the selected option.'),
      ],
    ];
    if ($deploy_plugin && $deploy_plugin->hasFormClass('configure')) {
      $subform_state = SubformState::createForSubform($form['deploySettings'], $form, $form_state);
      $settings_form = $deploy_plugin->buildConfigurationForm($form['deploySettings'], $subform_state);
      if ($settings_form) {
        $form['deploySettings'] += $settings_form;
        unset($form['deploySettings']['__none']);
      }
    }

    return $form;
  }

  /**
   * Submission handler.
   */
  public function rebuildForm(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->entity = $this->buildEntity($form, $form_state);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback.
   */
  public function updateDeployForm(array $form, FormStateInterface $form_state) {
    $build = $form['deploySettings'] ?? [];
    $build['messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];
    return $build;
  }

  /**
   * Ajax callback.
   */
  public function updateGenerateForm(array $form, FormStateInterface $form_state) {
    $build = $form['generateSettings'] ?? [];
    $build['messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\preview_site\Entity\PreviewStrategyInterface $strategy */
    $strategy = $this->entity;
    if (($deploy_plugin = $strategy->getDeployPlugin()) && $deploy_plugin->hasFormClass('configure') && isset($form['deploySettings'])) {
      $subform_state = SubformState::createForSubform($form['deploySettings'], $form, $form_state);
      $deploy_plugin->validateConfigurationForm($form['deploySettings'], $subform_state);
    }
    if (($generate_plugin = $strategy->getGeneratePlugin()) && $generate_plugin->hasFormClass('configure') && isset($form['generateSettings'])) {
      $subform_state = SubformState::createForSubform($form['generateSettings'], $form, $form_state);
      $generate_plugin->validateConfigurationForm($form['generateSettings'], $subform_state);
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\preview_site\Entity\PreviewStrategyInterface $strategy */
    $strategy = $this->entity;
    if (($deploy_plugin = $strategy->getDeployPlugin()) && $deploy_plugin->hasFormClass('configure') && isset($form['deploySettings'])) {
      $subform_state = SubformState::createForSubform($form['deploySettings'], $form, $form_state);
      $deploy_plugin->submitConfigurationForm($form['deploySettings'], $subform_state);
    }
    if (($generate_plugin = $strategy->getGeneratePlugin()) && $generate_plugin->hasFormClass('configure') && isset($form['generateSettings'])) {
      $subform_state = SubformState::createForSubform($form['generateSettings'], $form, $form_state);
      $generate_plugin->submitConfigurationForm($form['generateSettings'], $subform_state);
    }
    $status = $strategy->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label Preview Strategy.', [
          '%label' => $strategy->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label Preview Strategy.', [
          '%label' => $strategy->label(),
        ]));
    }
    $form_state->setRedirectUrl($strategy->toUrl('collection'));
  }

}
