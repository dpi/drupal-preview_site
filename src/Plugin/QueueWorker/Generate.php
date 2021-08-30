<?php

namespace Drupal\preview_site\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a queue worker plugin for generators.
 *
 * @QueueWorker(
 *   id = "preview_site_generate",
 *   title = @Translation("Generate queue worker"),
 *   deriver = \Drupal\preview_site\Plugin\Derivative\PreviewSiteBuildQueueWorkerDeriver::class,
 * )
 */
class Generate extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $log;

  /**
   * Queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a new Generate.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $log
   *   Log.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LoggerChannelInterface $log, QueueFactory $queueFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->log = $log;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('preview_site'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build */
    $build = $this->entityTypeManager->getStorage('preview_site_build')->load($this->pluginDefinition['preview_site_build_id']);
    $item = $build->get('contents')->get($data);
    $needs_save = FALSE;
    $strategy = $build->getStrategy();
    if (!$strategy) {
      // This could only occur if the strategy was deleted but the queue was
      // populated. In this scenario, we assume there's nothing to do.
      $message = sprintf('Preview site build %s (%d) is not associated with a build strategy.', $build->label(), $build->id());
      $build->addLogEntry($message);
      $this->log->error($message);
      return;
    }
    $deploy = $strategy->getDeployPlugin();
    if (!$deploy) {
      // This could only occur if the strategy was misconfigured.
      $message = sprintf('Preview site build %s (%d) is configured to use the %s strategy, but the deploy plugin for that strategy is not configured.', $build->label(), $build->id(), $strategy->label());
      $build->addLogEntry($message);
      $this->log->error($message);
      return;
    }
    $base_uri = $deploy->getDeploymentBaseUri($build);
    if (!$base_uri) {
      // The deploy plugin is not correctly configured.
      // Log and continue.
      $message = sprintf('Preview site build %s (%d) is configured to use the %s strategy, but the %s deploy plugin did not provide a base URL for deployment.', $build->label(), $build->id(), $strategy->label(), $strategy->getDeployPluginId());
      $build->addLogEntry($message);
      $this->log->error($message);
      return;
    }
    if ($item && $collection = $strategy->getGeneratePlugin()->generateBuildForItem($build, $item, $base_uri, $this->queueFactory->get('preview_site_assets:' . $build->id()))) {
      // Store build artifacts.
      foreach ($collection as $file) {
        $build->addArtifact($file, FALSE);
        $needs_save = TRUE;
      }
    }
    if ($needs_save) {
      $build->save();
    }
  }

}
