<?php

namespace Drupal\preview_site\Generate;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\PreviewSitePluginInterface;

/**
 * Defines an interface for preview site generate plugins.
 */
interface GeneratePluginInterface extends PreviewSitePluginInterface {

  const PARENT_NEGOTIATION_HANDLER = 'preview_site_parent';

  /**
   * Generates a build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   The build to generate.
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item
   *   The item being built.
   * @param string $base_url
   *   Base URL.
   * @param \Drupal\Core\Queue\QueueInterface $asset_queue
   *   Asset queue.
   *
   * @return \Drupal\preview_site\Generate\FileCollection
   *   Artifact files generated during the build.
   */
  public function generateBuildForItem(PreviewSiteBuildInterface $build, EntityReferenceItem $item, string $base_url, QueueInterface $asset_queue) : FileCollection;

  /**
   * Prepares for generating a build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   * @param string|null $base_url
   *   Base URL.
   */
  public function prepareBuild(PreviewSiteBuildInterface $build, ?string $base_url);

  /**
   * Completes generation of a build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   */
  public function completeBuild(PreviewSiteBuildInterface $build);

  /**
   * Allows generate plugins to implement hook_entity_preload.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   * @param array $ids
   *   Entity IDs.
   * @param string $entity_type_id
   *   Entity type ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of pre-loaded entities.
   */
  public function entityPreload(PreviewSiteBuildInterface $build, array $ids, string $entity_type_id, EntityTypeManagerInterface $entity_type_manager) : array;

  /**
   * Generates a build for an asset path.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   The build to generate.
   * @param string $path
   *   Asset path.
   * @param string $base_url
   *   Base URL.
   * @param \Drupal\Core\Queue\QueueInterface $asset_queue
   *   Asset queue.
   *
   * @return \Drupal\preview_site\Generate\FileCollection
   *   Artifact files generated during the build.
   */
  public function generateBuildForPath(PreviewSiteBuildInterface $build, string $path, string $base_url, QueueInterface $asset_queue) : FileCollection;

  /**
   * Checks 'view' access for an entity being rendered during generation.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build being generated.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being access checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account checking access. This will typically be an anonymous user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function entityAccess(PreviewSiteBuildInterface $build, ContentEntityInterface $entity, AccountInterface $account, EntityTypeManagerInterface $entityTypeManager) : AccessResultInterface;

  /**
   * Allows generate plugins to interact with entity-queries.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build being generated.
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   *   Query being run.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   */
  public function entityQueryAlter(PreviewSiteBuildInterface $build, AlterableInterface $query, EntityTypeInterface $entity_type);

  /**
   * Gets artifact base path.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   *
   * @return string
   *   Base path.
   */
  public function getArtifactBasePath(PreviewSiteBuildInterface $build) : string;

}
