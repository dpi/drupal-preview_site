<?php

namespace Drupal\preview_site\EntityHandlers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;

/**
 * Defines a list builder for the preview_site_build entity.
 *
 * @codeCoverageIgnore
 * @see \Drupal\Tests\preview_site\Functional\PreviewStrategyAdministrationTest
 * @see \Drupal\Tests\preview_site\Functional\PreviewStrategyAdministrationTest
 */
class PreviewSiteBuildListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      $this->t('Label'),
      $this->t('Strategy'),
      $this->t('Status'),
      $this->t('Item count'),
      $this->t('Item links'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof PreviewSiteBuildInterface);
    return [
      $entity->toLink(),
      $entity->getStrategyLabel(),
      $entity->getStatus(),
      $entity->getItemCount(),
      ['data' => $this->buildItemLinks($entity)],
    ] + parent::buildRow($entity);
  }

  /**
   * Gets item links.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   *
   * @return string[]
   *   Links to the build items.
   */
  protected function buildItemLinks(PreviewSiteBuildInterface $build): array {
    $deployment_uri = $build->getDeploymentBaseUri();
    if (!$deployment_uri) {
      return ['#markup' => ''];
    }
    return [
      '#type' => 'operations',
      '#links' => $build->getItemLinks(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface $entity */
    $operations = parent::getDefaultOperations($entity);
    if ($entity->hasLinkTemplate('deploy-form')) {
      $operations['deploy'] = [
        'title' => $this->t('Build and Deploy'),
        'weight' => $entity->getStatus() === PreviewSiteBuildInterface::STATUS_PENDING ? -10 : 50,
        'url' => $entity->toUrl('deploy-form'),
      ];
    }
    if (in_array($entity->getStatus(), [PreviewSiteBuildInterface::STATUS_BUILT, PreviewSiteBuildInterface::STATUS_FAILED])) {
      $operations['view_logs'] = [
        'title' => $this->t('View logs'),
        'weight' => -10,
        'url' => $entity->toUrl(),
      ];
    }

    return $operations;
  }

}
