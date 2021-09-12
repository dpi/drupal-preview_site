<?php

namespace Drupal\preview_site\Generate;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\PreviewSitePluginTrait;

/**
 * Defines a base generate plugin.
 */
abstract class GeneratePluginBase extends PluginBase implements GeneratePluginInterface {

  use PreviewSitePluginTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareBuild(PreviewSiteBuildInterface $build, ?string $base_url) {
    // nil-op.
  }

  /**
   * {@inheritdoc}
   */
  public function completeBuild(PreviewSiteBuildInterface $build) {
    // nil-op.
  }

  /**
   * {@inheritdoc}
   */
  public function entityPreload(PreviewSiteBuildInterface $build, array $ids, string $entity_type_id, EntityTypeManagerInterface $entity_type_manager): array {
    $storage = $entity_type_manager->getStorage($entity_type_id);
    if (!($storage instanceof RevisionableStorageInterface) || !$entity_type_manager->getDefinition($entity_type_id)->getRevisionDataTable()) {
      // Nothing to do here.
      return [];
    }
    $revision_ids = [];
    foreach ($ids as $entity_id) {
      $revision_ids[] = $storage->getLatestRevisionId($entity_id);
    }
    $entities = [];
    foreach ($storage->loadMultipleRevisions(array_filter($revision_ids)) as $revision) {
      $entities[$revision->id()] = $revision;
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(PreviewSiteBuildInterface $build, ContentEntityInterface $entity, AccountInterface $account, EntityTypeManagerInterface $entityTypeManager): AccessResultInterface {
    return (new AccessResultNeutral())->setCacheMaxAge(0);
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(PreviewSiteBuildInterface $build, AlterableInterface $query, EntityTypeInterface $entity_type) {
    // nil-op.
  }

}
