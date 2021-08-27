<?php

namespace Drupal\preview_site;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;

/**
 * Defines a class for setting a preview site cache context.
 *
 * Note that we force this to be a mandatory context to ensure that nothing from
 * the preview site building process leaks into the main site.
 *
 * @see parameters.renderer.config.required_cache_contexts in services.yml
 */
class PreviewSiteCacheContext implements CacheContextInterface {

  const NO_ACTIVE_PREVIEW_SITE_GENERATION = -1;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new PreviewSiteCacheContext.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity repository.
   */
  public function __construct(StateInterface $state, EntityRepositoryInterface $entityRepository) {
    $this->state = $state;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return new TranslatableMarkup('Preview site');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    if (($building = $this->state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY)) && $build = $this->entityRepository->loadEntityByUuid('preview_site_build', $building)) {
      return $build->id();
    }
    return self::NO_ACTIVE_PREVIEW_SITE_GENERATION;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $cache = new CacheableMetadata();
    $cache->addCacheTags(['preview_site_build_list']);
    if (($building = $this->state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY)) && $build = $this->entityRepository->loadEntityByUuid('preview_site_build', $building)) {
      return $cache->addCacheableDependency($build);
    }
    return $cache;
  }

}
