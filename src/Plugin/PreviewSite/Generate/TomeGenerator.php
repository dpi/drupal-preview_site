<?php

namespace Drupal\preview_site\Plugin\PreviewSite\Generate;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\EntityHandlers\ParentNegotiation\ParentNegotiationInterface;
use Drupal\preview_site\Generate\CouldNotWriteFileException;
use Drupal\preview_site\Generate\FileCollection;
use Drupal\preview_site\Generate\FileHelper;
use Drupal\preview_site\Generate\GeneratePluginBase;
use Drupal\tome_base\PathTrait;
use Drupal\tome_static\StaticGeneratorInterface;
use Drupal\tome_static\TomeStaticHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a default generator plugin.
 *
 * @PreviewSiteGenerate(
 *   id = "preview_site_tome",
 *   title = @Translation("Tome"),
 *   description = @Translation("Use Tome static for generation."),
 * )
 */
class TomeGenerator extends GeneratePluginBase {

  use PathTrait;

  /**
   * The static generator.
   *
   * @var \Drupal\preview_site\Generate\TomeStaticExtension
   */
  protected $static;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The request preparer.
   *
   * @var \Drupal\tome_static\RequestPreparer
   */
  protected $requestPreparer;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * File saved listener.
   *
   * @var \Drupal\preview_site\EventSubscribers\TomeStaticListener
   */
  protected $fileSavedListener;

  /**
   * Static cache.
   *
   * @var \Drupal\tome_static\StaticCacheInterface|\Drupal\Core\Cache\CacheBackendInterface
   */
  protected $tomeStaticCache;

  /**
   * Entity usage.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->static = $container->get('preview_site.tome_static');
    $instance->requestPreparer = $container->get('tome_static.request_preparer');
    $instance->requestStack = $container->get('request_stack');
    $instance->fileSystem = $container->get('file_system');
    $instance->state = $container->get('state');
    $instance->fileSavedListener = $container->get('preview_site.tome_file_saved_listener');
    $instance->tomeStaticCache = $container->get('cache.tome_static');
    $instance->entityUsage = $container->get('entity_usage.usage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function generateBuildForItem(PreviewSiteBuildInterface $build, EntityReferenceItem $item, string $base_url, QueueInterface $asset_queue): FileCollection {
    // We don't load the content entity yet, so that loading occurs in the scope
    // of the tome-generation.
    $path = sprintf('_entity:%s:%s:%s', $item->target_type, $build->language()->getId(), $item->target_id);
    return $this->generateBuildForPath($build, $path, $base_url, $asset_queue);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareBuild(PreviewSiteBuildInterface $build, ?string $base_url) {
    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, TRUE);
    $this->state->set(StaticGeneratorInterface::STATE_KEY_URL, $base_url);
    $this->static->setStaticDirectory('private://preview-site/' . $build->uuid());
    $this->static->prepareStaticDirectory();
    $this->tomeStaticCache->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function completeBuild(PreviewSiteBuildInterface $build) {
    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, FALSE);
    $this->static->setStaticDirectory('private://preview-site/' . $build->uuid());
    $this->static->cleanupStaticDirectory();
  }

  /**
   * {@inheritdoc}
   */
  public function entityPreload(PreviewSiteBuildInterface $build, array $ids, string $entity_type_id, EntityTypeManagerInterface $entity_type_manager): array {
    if ($entity_type_id === 'paragraph') {
      // ERR ensures the right revision is loaded here.
      return [];
    }
    if ($this->state->get(StaticGeneratorInterface::STATE_KEY_BUILDING) && $this->static->isGenerating()) {
      return parent::entityPreload($build, $ids, $entity_type_id, $entity_type_manager);
    }
    // We don't want to do anything unless there's a tome build in progress.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function generateBuildForPath(PreviewSiteBuildInterface $build, string $path, string $base_url, QueueInterface $asset_queue): FileCollection {
    $request = $this->requestStack->getCurrentRequest();
    $build->markPathAsProcessed($path);
    $this->static->setStaticDirectory('private://preview-site/' . $build->uuid());
    $original_params = TomeStaticHelper::setBaseUrl($request, $base_url);

    $this->requestPreparer->prepareForRequest();
    $this->fileSavedListener->resetLastFileWritten();
    try {
      $this->static->setIsGenerating(TRUE);
      $invoke_paths = $this->static->requestPath($path);
    }
    catch (\Exception $e) {
      $build->addLogEntry(sprintf('ERROR: Exception caught when requesting path %s in %s, line %s: %s',
        $path,
        $e->getFile(),
        $e->getLine(),
        $e->getMessage()
      ), FALSE);
      $build->deploymentFailed($this->state, FALSE);
      TomeStaticHelper::restoreBaseUrl($request, $original_params);
      return new FileCollection();
    }
    finally {
      $this->static->setIsGenerating(FALSE);
    }

    TomeStaticHelper::restoreBaseUrl($request, $original_params);

    if (!($destination = $this->fileSavedListener->getLastFileWritten())) {
      $build->addLogEntry(sprintf('WARNING: The anonymous user does not have access to view the latest revision for %s: skipped.',
        $path
      ));
      return new FileCollection();
    }
    $this->static->resetCopiedPaths();
    $remaining_assets = $this->static->exportPaths($invoke_paths);
    $copied_paths = $this->static->getCopiedPaths();

    foreach ($remaining_assets as $asset) {
      $asset_destination = $this->static->getDestination($asset);
      if (substr($asset_destination, -1 * strlen('/index.html')) === '/index.html' && !preg_match('@^/media/oembed\?@', $asset)) {
        // We don't want to crawl the site for other content, we only want to
        // get CSS, Javascript, images etc.
        continue;
      }
      if (!$build->hasPathBeenProcessed($asset)) {
        $asset_queue->createItem($asset);
      }
    }

    try {
      $collection = new FileCollection(FileHelper::createFromExistingFile($destination));
      foreach (array_unique($copied_paths) as $copied_path) {
        // Remove any relative slashes (./) but retain ../.
        $copied_path = preg_replace('@(?<!\.)\./@', '', $copied_path);
        if ($build->hasPathBeenProcessed($copied_path)) {
          continue;
        }
        $build->markPathAsProcessed($copied_path);
        $collection->addFile(FileHelper::createFromExistingFile($copied_path));
      }
      return $collection;
    }
    catch (CouldNotWriteFileException $e) {
      $build->addLogEntry(sprintf('ERROR: Exception caught when attempting to create file from %s: %s',
        $path,
        $e->getMessage()
      ), FALSE);
      $build->deploymentFailed($this->state, FALSE);
    }
    return new FileCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(PreviewSiteBuildInterface $build, ContentEntityInterface $entity, AccountInterface $account, EntityTypeManagerInterface $entityTypeManager): AccessResultInterface {
    return AccessResult::allowedIf($this->static->isGenerating() && $this->entityIsRelevantToBuild($build, $entity, $entityTypeManager))->setCacheMaxAge(0);
  }

  /**
   * Checks if an entity is relevant to this preview-site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @return bool
   *   TRUE if is available.
   */
  protected function entityIsRelevantToBuild(PreviewSiteBuildInterface $build, ContentEntityInterface $entity, EntityTypeManagerInterface $entityTypeManager): bool {
    $stack = new \SplObjectStorage();
    $stack->offsetSet($this, 0);
    return !empty($build->getMatchingContents([$entity->id()], $entity->getEntityTypeId())) || $this->hasRelatedUsage($build, $entity, $stack, $entityTypeManager);
  }

  /**
   * Checks if an entity has a related usage to content in the build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param \SplObjectStorage $seen
   *   Items already tested.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @return bool
   *   TRUE if is available.
   */
  protected function hasRelatedUsage(PreviewSiteBuildInterface $build, ContentEntityInterface $entity, \SplObjectStorage $seen, EntityTypeManagerInterface $entityTypeManager) : bool {
    if ($seen->offsetExists($entity)) {
      return $seen->offsetGet($entity);
    }
    if ($negotiator = $entityTypeManager->getHandler($entity->getEntityTypeId(), self::PARENT_NEGOTIATION_HANDLER)) {
      assert($negotiator instanceof ParentNegotiationInterface);
      if (($parent = $negotiator->getParentEntity($entity)) && !empty($build->getMatchingContents([$parent->id()], $parent->getEntityTypeId()))) {
        $seen->offsetSet($entity, TRUE);
        $seen->offsetSet($parent, TRUE);
        return TRUE;
      }
    }
    $usages = $this->entityUsage->listSources($entity);
    foreach ($usages as $entity_type_id => $items) {
      $entity_ids = array_keys($items);
      if (!empty($build->getMatchingContents($entity_ids, $entity_type_id))) {
        $seen->offsetSet($entity, TRUE);
        return TRUE;
      }
      $dependant_entity_type = $entityTypeManager->getDefinition($entity_type_id);
      if (!$dependant_entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      // Prevent recursion.
      $seen->offsetSet($entity, FALSE);
      foreach ($entityTypeManager->getStorage($entity_type_id)->loadMultiple($entity_ids) as $dependant_entity) {
        $depth = $seen->offsetGet($this);
        if ($depth >= 10) {
          // Prevent nesting deeper than 10 layers.
          $seen->offsetSet($entity, FALSE);
          return FALSE;
        }
        $seen->offsetSet($this, $depth + 1);
        if ($this->hasRelatedUsage($build, $dependant_entity, $seen, $entityTypeManager)) {
          $seen->offsetSet($entity, TRUE);
          return TRUE;
        }
      }
    }
    $seen->offsetSet($entity, FALSE);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(PreviewSiteBuildInterface $build, AlterableInterface $query, EntityTypeInterface $entity_type) {
    if (!$this->static->isGenerating()) {
      return;
    }
    parent::entityQueryAlter($build, $query, $entity_type);
    if (!($published_key = $entity_type->getKey('published'))) {
      return;
    }
    if (!($id_key = $entity_type->getKey('id'))) {
      return;
    }
    $status_field = $entity_type->getDataTable() . '.' . $published_key;
    $conditions = &$query->conditions();
    foreach ($conditions as $key => $condition) {
      if ($key === '#conjunction') {
        continue;
      }
      if ($condition['field'] !== $status_field) {
        continue;
      }
      $ids_of_type = $build->getContentsOfType($entity_type->id());
      if (!$ids_of_type) {
        continue;
      }
      $group = $query->orConditionGroup()
        ->condition($condition['field'], $condition['value'], $condition['operator'])
        ->condition($entity_type->getDataTable() . '.' . $id_key, $ids_of_type, 'IN');
      $conditions[$key] = [
        'field' => $group,
        'value' => NULL,
        'operator' => '=',
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getArtifactBasePath(PreviewSiteBuildInterface $build): string {
    return 'private://preview-site/' . $build->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string {
    return $url . '/index.html';
  }

}
