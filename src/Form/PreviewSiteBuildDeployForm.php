<?php

namespace Drupal\preview_site\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\PreviewSiteBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for deploying a preview site.
 *
 * @codeCoverageIgnore
 * @see \Drupal\Tests\preview_site\Functional\PreviewSiteBuildDeployFormTest
 */
class PreviewSiteBuildDeployForm extends ContentEntityConfirmFormBase {

  /**
   * Entity.
   *
   * @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface
   */
  protected $entity;

  /**
   * Builder.
   *
   * @var \Drupal\preview_site\PreviewSiteBuilder
   */
  protected $builder;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->builder = $container->get('class_resolver')->getInstanceFromDefinition(PreviewSiteBuilder::class);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state) + [
      'clear_lock' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force reset lock'),
        '#access' => (bool) $this->state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY, FALSE),
        '#description' => $this->t('An existing preview site is being built, or has failed. You cannot proceed without force resetting the lock. If another build is in process, this will have unintended side-effects.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return new TranslatableMarkup('Are you sure you wish to build and deploy this Preview site?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.preview_site_build.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return new TranslatableMarkup('This will launch a batch process to generate a preview of each content item. The site will be ready once the batch process is complete.');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY, FALSE) && !$form_state->getValue('clear_lock')) {
      $form_state->setErrorByName('clear_lock', $this->t('Another build is in process or has failed, deployment cannot proceed without force-resetting.'));
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    if ($form_state->getValue('clear_lock')) {
      $this->state->delete(PreviewSiteBuildInterface::BUILDING_STATE_KEY);
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
    batch_set($this->builder->getPreviewSiteBuildBatch($this->entity)->toArray());
  }

}
