<?php

namespace Drupal\preview_site\EntityHandlers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a list builder for the PreviewStrategy entity.
 *
 * @codeCoverageIgnore
 * @see \Drupal\Tests\preview_site\Functional\PreviewStrategyAdministrationTest
 */
class PreviewStrategyListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'label' => $this->t('Label'),
      'generate' => $this->t('Generate using'),
      'deploy' => $this->t('Deploy to'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\preview_site\Entity\PreviewStrategyInterface $entity */
    return [
      'label' => $entity->toLink(NULL, 'edit-form'),
      'generate' => $entity->getGeneratePlugin()->getTitle(),
      'deploy' => $entity->getDeployPlugin()->getTitle(),
    ] + parent::buildRow($entity);
  }

}
