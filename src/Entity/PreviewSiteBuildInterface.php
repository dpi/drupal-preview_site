<?php

namespace Drupal\preview_site\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileInterface;
use Drupal\preview_site\Deploy\DeployPluginInterface;
use Drupal\preview_site\Generate\GeneratePluginInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines an interface for a preview site build content-entity.
 */
interface PreviewSiteBuildInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, RevisionLogInterface {

  const BUILDING_STATE_KEY = 'preview_state_building';
  const STATUS_PENDING = 'pending';
  const STATUS_BUILDING = 'building';
  const STATUS_BUILT = 'built';
  const STATUS_FAILED = 'failed';
  const STATUS_STALE = 'stale';
  const STATUS_DECOMMISSIONED = 'decommissioned';

  /**
   * Gets strategy label.
   *
   * @return string
   *   Label.
   */
  public function getStrategyLabel(): string;

  /**
   * Gets status.
   *
   * @return string|null
   *   Status.
   */
  public function getStatus(): ?string;

  /**
   * Gets item count.
   *
   * @return int
   *   Item count.
   */
  public function getItemCount(): int;

  /**
   * Gets the preview strategy.
   *
   * @return \Drupal\preview_site\Entity\PreviewStrategyInterface
   *   Preview strategy.
   */
  public function getStrategy(): ?PreviewStrategyInterface;

  /**
   * Queues generation.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   Queue to use for generation.
   */
  public function queueGeneration(QueueInterface $queue) : void;

  /**
   * Queues deployment.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   Queue to use for deployment.
   */
  public function queueDeployment(QueueInterface $queue) : void;

  /**
   * Finishes deployment.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   *
   * @return $this
   */
  public function finishDeployment(StateInterface $state) : PreviewSiteBuildInterface;

  /**
   * Gets the artifact IDs.
   *
   * @return array
   *   File IDs for build artifacts.
   */
  public function getArtifactIds(): array;

  /**
   * Starts deployment.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   *
   * @return int[]
   *   Array of file IDs to cleanup before starting the deployment.
   *
   * @throws \Drupal\preview_site\Generate\GenerationInProgressException
   *   When an existing deployment is in progress.
   */
  public function startDeployment(StateInterface $state) : array;

  /**
   * Gets deployment URI.
   *
   * @return string|null
   *   Deployment URI.
   */
  public function getDeploymentBaseUri() : ?string;

  /**
   * Marks deployment as failed.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param bool $reset
   *   TRUE to reset building state.
   */
  public function deploymentFailed(StateInterface $state, bool $reset = TRUE) : void;

  /**
   * Checks if the build failed.
   *
   * @return bool
   *   TRUE if failed.
   */
  public function isFailed() : bool;

  /**
   * Gets entities that match contents of this build.
   *
   * @param array $ids
   *   Entity IDs to match.
   * @param string $entity_type_id
   *   Entity type IDs.
   *
   * @return array
   *   Matching entity IDs.
   */
  public function getMatchingContents(array $ids, string $entity_type_id): array;

  /**
   * Adds a log entry.
   *
   * @param string $entry
   *   Entry.
   * @param bool $auto_save
   *   True to auto-save.
   */
  public function addLogEntry(string $entry, bool $auto_save = TRUE) : void;

  /**
   * Adds an artifact.
   *
   * @param \Drupal\file\FileInterface $file
   *   File.
   * @param bool $auto_save
   *   True to auto-save.
   */
  public function addArtifact(FileInterface $file, bool $auto_save = TRUE) : void;

  /**
   * Check if a path has been processed.
   *
   * @param string $path
   *   Path to check.
   *
   * @return bool
   *   TRUE if it has been processed.
   */
  public function hasPathBeenProcessed(string $path) : bool;

  /**
   * Marks a path as processed.
   *
   * @param string $path
   *   Path to mark as processed.
   * @param bool $auto_save
   *   TRUE to auto-save.
   */
  public function markPathAsProcessed(string $path, bool $auto_save = TRUE) : void;

  /**
   * Gets the generate plugin for this build.
   *
   * @return \Drupal\preview_site\Generate\GeneratePluginInterface|null
   *   Generate plugin.
   */
  public function getGeneratePlugin() : ?GeneratePluginInterface;

  /**
   * Gets entity IDs that of given entity-type that are part of the build.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return array
   *   IDs for entities that are of the given type and part of the build.
   */
  public function getContentsOfType(string $entity_type_id) : array;

  /**
   * Gets artifact base path.
   *
   * @return string|null
   *   Base path.
   */
  public function getArtifactBasePath() : ?string;

  /**
   * Gets expiry date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   Expiry date.
   */
  public function getExpiryDate() : ?DrupalDateTime;

  /**
   * Decomissions a preview site.
   */
  public function decomission() : void;

  /**
   * Gets the deploy plugin for this build.
   *
   * @return \Drupal\preview_site\Deploy\DeployPluginInterface|null
   *   Deploy plugin.
   */
  public function getDeployPlugin() : ?DeployPluginInterface;

  /**
   * Gets artifacts.
   *
   * @return \Drupal\file\FileInterface[]|\Generator
   *   Array of files.
   */
  public function getArtifacts() : \Generator;

  /**
   * Gets item links for a deployed site.
   *
   * @return array
   *   Item links. Each must have a title, weight and url key like entity
   *   operations.
   */
  public function getItemLinks(): array;

}
