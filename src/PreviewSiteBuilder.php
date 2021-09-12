<?php

namespace Drupal\preview_site;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for building a preview site.
 */
class PreviewSiteBuilder implements ContainerInjectionInterface {

  /**
   * Queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Queue manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Entity-repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new PreviewSiteBuilder.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
   *   Queue manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity-repository.
   */
  public function __construct(QueueFactory $queueFactory, QueueWorkerManagerInterface $queueManager, StateInterface $state, EntityRepositoryInterface $entityRepository) {
    $this->queueFactory = $queueFactory;
    $this->queueManager = $queueManager;
    $this->state = $state;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('state'),
      $container->get('entity.repository')
    );
  }

  /**
   * Returns a new instance of the preview site builder.
   *
   * @return \Drupal\preview_site\PreviewSiteBuilder
   *   Instance of the preview site builder.
   */
  public static function factory() : PreviewSiteBuilder {
    return \Drupal::classResolver(self::class);
  }

  /**
   * Queues required tasks to generate a preview site.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site to generate.
   */
  public function queueSiteGeneration(PreviewSiteBuildInterface $build) {
    $asset_queue = $this->queueFactory->get('preview_site_assets:' . $build->id());
    $generation_queue = $this->queueFactory->get('preview_site_generate:' . $build->id());
    foreach ([$asset_queue, $generation_queue] as $queue) {
      $queue->deleteQueue();
      $queue->createQueue();
    }
    $build->queueGeneration($generation_queue);
  }

  /**
   * Processes a single generation task.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site to generate.
   *
   * @return int
   *   Remaining items to process.
   */
  public function processSiteGeneration(PreviewSiteBuildInterface $build) : int {
    return $this->processQueueItem('preview_site_generate:' . $build->id());
  }

  /**
   * Processes a single asset generation task.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site to generate.
   *
   * @return int
   *   Remaining items to process.
   */
  public function processAssetGeneration(PreviewSiteBuildInterface $build) : int {
    return $this->processQueueItem('preview_site_assets:' . $build->id());
  }

  /**
   * Queues required tasks to deploy a preview site.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site to deploy.
   */
  public function queueSiteDeployment(PreviewSiteBuildInterface $build) {
    $deploy_queue = $this->queueFactory->get('preview_site_deploy:' . $build->id());
    $deploy_queue->deleteQueue();
    $deploy_queue->createQueue();
    $build->queueDeployment($deploy_queue);
  }

  /**
   * Processes a single generation task.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site to generate.
   *
   * @return int
   *   Remaining items to process.
   */
  public function processSiteDeployment(PreviewSiteBuildInterface $build) : int {
    return $this->processQueueItem('preview_site_deploy:' . $build->id());
  }

  /**
   * Processes a queue item.
   *
   * @param string $queue_name
   *   Queue name.
   *
   * @return int
   *   Number of remaining items.
   */
  protected function processQueueItem(string $queue_name) : int {
    $queue = $this->queueFactory->get($queue_name);
    $item = $queue->claimItem();
    if (!$item) {
      return 0;
    }
    $queue->createQueue();
    $worker = $this->queueManager->createInstance($queue_name);
    try {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
    catch (DelayedRequeueException $e) {
      if ($queue instanceof DelayableQueueInterface) {
        $queue->delayItem($item, $e->getDelay());
        return $queue->numberOfItems();
      }
      $queue->releaseItem($item);
    }
    catch (RequeueException $e) {
      $queue->releaseItem($item);
    }
    return $queue->numberOfItems();
  }

  /**
   * Gets a pre-build batch for building a preview-site.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Site build to generate.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   Batch built with tasks for this build.
   */
  public function getPreviewSiteBuildBatch(PreviewSiteBuildInterface $build) : BatchBuilder {
    return (new BatchBuilder())
      ->setTitle(new TranslatableMarkup('Building preview site'))
      ->setInitMessage(new TranslatableMarkup('Preparing...'))
      ->setProgressive(TRUE)
      ->setFinishCallback([self::class, 'finished'])
      ->setProgressMessage(new TranslatableMarkup('Step @current of @total'))
      ->addOperation([self::class, 'operationMarkDeploymentStarted'], [$build->id()])
      ->addOperation([self::class, 'operationQueueGenerate'], [$build->id()])
      ->addOperation([self::class, 'operationProcessGenerate'], [$build->id()])
      ->addOperation([self::class, 'operationProcessAssets'], [$build->id()])
      ->addOperation([self::class, 'operationQueueDeploy'], [$build->id()])
      ->addOperation([self::class, 'operationProcessDeploy'], [$build->id()])
      ->addOperation([self::class, 'operationMarkDeploymentFinished'], [$build->id()]);
  }

  /**
   * Batch callback.
   */
  public static function operationMarkDeploymentStarted(int $build_id, array &$context) {
    $context['results']['build_id'] = $build_id;
    PreviewSiteBuild::load($build_id)->startDeployment(\Drupal::state());
    $context['message'] = new TranslatableMarkup('Marked deployment as building');
  }

  /**
   * Batch callback.
   */
  public static function operationQueueGenerate(int $build_id) {
    self::factory()->queueSiteGeneration(PreviewSiteBuild::load($build_id));
    $context['message'] = new TranslatableMarkup('Queued content items for preview generation');
  }

  /**
   * Batch callback.
   */
  public static function operationProcessGenerate(int $build_id, array &$context) {
    $remaining = self::factory()->processSiteGeneration(PreviewSiteBuild::load($build_id));
    self::updateFinishedPercent($remaining, $context);
    $context['results']['generated'] = ($context['results']['generated'] ?? 0) + 1;
    $context['message'] = new PluralTranslatableMarkup($remaining, 'Generating previews for content items (1 item remaining)', 'Generating previews for content items (@count items remaining)');
  }

  /**
   * Batch callback.
   */
  public static function operationProcessAssets(int $build_id, array &$context) {
    $remaining = self::factory()->processAssetGeneration(PreviewSiteBuild::load($build_id));
    self::updateFinishedPercent($remaining, $context);
    $context['results']['assets'] = ($context['results']['assets'] ?? 0) + 1;
    $context['message'] = new PluralTranslatableMarkup($remaining, 'Generating assets for content items (1 item remaining)', 'Generating assets for content items (@count items remaining)');
  }

  /**
   * Batch callback.
   */
  public static function operationQueueDeploy(int $build_id) {
    self::factory()->queueSiteDeployment(PreviewSiteBuild::load($build_id));
    $context['message'] = new TranslatableMarkup('Queued artifacts for deployment');
  }

  /**
   * Batch callback.
   */
  public static function operationProcessDeploy(int $build_id, array &$context) {
    $remaining = self::factory()->processSiteDeployment(PreviewSiteBuild::load($build_id));
    self::updateFinishedPercent($remaining, $context);
    $context['results']['deployed'] = ($context['results']['deployed'] ?? 0) + 1;
    $context['message'] = new PluralTranslatableMarkup($remaining, 'Deploying artifacts (1 item remaining)', 'Deploying artifacts (@count items remaining)');
  }

  /**
   * Batch callback.
   */
  public static function operationMarkDeploymentFinished(int $build_id, array &$context) {
    /** @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build */
    $build = PreviewSiteBuild::load($build_id);
    if (!isset($context['sandbox']['clean_up_file_ids'])) {
      $context['sandbox']['clean_up_file_ids'] = $build->finishDeployment(\Drupal::state())->getArtifactIds();
      $context['sandbox']['total'] = count($context['sandbox']['clean_up_file_ids']);
    }
    if ($fids = array_splice($context['sandbox']['clean_up_file_ids'], 0, 10)) {
      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $file_storage->delete($file_storage->loadMultiple($fids));
    }
    self::updateFinishedPercent(count($context['sandbox']['clean_up_file_ids']), $context);
    if ($context['finished'] == 1) {
      $status = $build->getStatus();
      $context['message'] = new TranslatableMarkup('Marked deployment as @status', [
        '@status' => $status,
      ]);
      if ($status === PreviewSiteBuildInterface::STATUS_FAILED) {
        $context['results']['generate_errors'] = TRUE;
      }
      return;
    }
    $context['message'] = new TranslatableMarkup('Deleting old artifacts');
  }

  /**
   * Batch finished callback.
   */
  public static function finished(bool $success, array $results, array $operations) {
    if ($success && empty($results['generate_errors'])) {
      \Drupal::messenger()->addMessage(new TranslatableMarkup('The preview site was successfully built, @generated previews were generated and @deployed artifacts were deployed.', [
        '@generated' => $results['generated'],
        '@deployed' => $results['deployed'],
      ]));
      return;
    }
    if (!empty($results['generate_errors'])) {
      \Drupal::messenger()->addError(new TranslatableMarkup('The preview site was not able to be built, preview generation failed.'));
      return;
    }
    if (!empty($results['build_id'])) {
      $build = PreviewSiteBuild::load($results['build_id']);
      if ($build->getStatus() !== PreviewSiteBuildInterface::STATUS_FAILED) {
        $build->deploymentFailed(\Drupal::state());
      }
    }
    \Drupal::messenger()->addError(new PluralTranslatableMarkup(
        count($operations),
        'The preview site was unable to be generated and deployed, one operation failed.',
        'The preview site was unable to be generated and deployed, @count operations failed.',
      )
    );
  }

  /**
   * Updates finished percent.
   *
   * @param int $remaining
   *   Remaining items.
   * @param array $context
   *   Batch context.
   */
  protected static function updateFinishedPercent(int $remaining, array &$context): void {
    if ($remaining === 0) {
      $context['finished'] = 1;
      return;
    }
    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = $remaining + 1;
    }
    $context['finished'] = ($context['sandbox']['total'] - $remaining) / $context['sandbox']['total'];
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    // The queue factory can't be serialized.
    return [];
  }

  /**
   * Gets the active preview site build if one is running.
   */
  public function getRunningBuild() : ?PreviewSiteBuildInterface {
    if (($building = $this->state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY)) &&
    ($build = $this->entityRepository->loadEntityByUuid('preview_site_build', $building))) {
      assert($build instanceof PreviewSiteBuildInterface);
      return $build;
    }
    return NULL;
  }

}
