<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Generate\GenerationInProgressException;
use Drupal\preview_site_test\Plugin\PreviewSite\Deploy\TestDeploy;
use Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Defines a class for testing the PreviewSite entity.
 *
 * @covers \Drupal\preview_site\Entity\PreviewSiteBuild
 * @covers \Drupal\preview_site\Entity\PreviewStrategy
 * @covers \Drupal\preview_site\Plugin\Derivative\PreviewSiteBuildQueueWorkerDeriver
 * @covers \Drupal\preview_site\Plugin\QueueWorker\Generate
 * @covers \Drupal\preview_site\Plugin\QueueWorker\GenerateAssets
 * @covers \Drupal\preview_site\Plugin\QueueWorker\Deploy
 * @covers \Drupal\preview_site\Generate\GeneratePluginBase
 *
 * @group preview_site
 */
class PreviewSiteBuildEntityTest extends PreviewSiteKernelTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('taxonomy_term');
  }

  /**
   * Tests build entity functionality.
   */
  public function testBasicCrudOperations() : void {
    $strategy_label = $this->randomMachineName();
    $label = $this->randomMachineName();
    $user = $this->createUser();
    $vocabulary = $this->createVocabulary();
    $term = $this->createTerm($vocabulary);
    $entity_test = EntityTest::create();
    $prefix = $this->randomMachineName();
    $strategy = $this->createStrategy($strategy_label, $this->randomMachineName(), $prefix);
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager */
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $this->assertCount(0, array_filter(array_keys($queue_manager->getDefinitions()), function (string $key) {
      return strpos($key, 'preview_site') === 0 && $key !== 'preview_site_decommission';
    }));
    $file = $this->getTestFile();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'label' => $label,
      'contents' => [
        $entity_test,
        $term,
        $user,
      ],
      'artifacts' => $file->id(),
      'log' => 'stuff',
      'processed_paths' => ['/some/path'],
    ]);
    $this->assertEquals(3, $build->getItemCount());
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_PENDING, $build->getStatus());
    $this->assertEquals($strategy_label, $build->getStrategyLabel());

    // Saving a build should create three queues for each item.
    $this->assertTrue($queue_manager->hasDefinition('preview_site_generate:' . $build->id()));
    $this->assertTrue($queue_manager->hasDefinition('preview_site_deploy:' . $build->id()));
    $this->assertTrue($queue_manager->hasDefinition('preview_site_assets:' . $build->id()));
    $build = \Drupal::entityTypeManager()->getStorage('preview_site_build')
      ->loadUnchanged($build->id());
    $contents = $build->get('contents')->getValue();
    $this->assertEquals([
      $entity_test->id(),
      $term->id(),
      $user->id(),
    ], array_column($contents, 'target_id'));
    $this->assertEquals([
      $entity_test->getEntityTypeId(),
      $term->getEntityTypeId(),
      $user->getEntityTypeId(),
    ], array_column($contents, 'target_type'));
    $this->assertEquals($label, $build->label());
    $this->assertEquals($strategy->label(), $build->strategy->entity->label());
    $this->assertEquals('stuff', $build->log->value);
    $this->assertEquals($file->id(), $build->artifacts->target_id);
    $this->assertEquals('/some/path', $build->processed_paths->value);
    $this->assertInstanceOf(TestGenerate::class, $build->getGeneratePlugin());

    $build->delete();

    // The queues should now be deleted.
    $this->assertCount(0, array_filter(array_keys($queue_manager->getDefinitions()), function (string $key) {
      return strpos($key, 'preview_site') === 0 && $key !== 'preview_site_decommission';
    }));
  }

  /**
   * Tests generate and deployment queues..
   */
  public function testGenerationAndDeploymentQueue() {
    $user = $this->createUser();
    $vocabulary = $this->createVocabulary();
    $term = $this->createTerm($vocabulary);
    $entity_test = EntityTest::create();
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        $entity_test,
        $term,
        $user,
      ],
    ]);
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $this->assertEquals(0, $generate_queue->numberOfItems());
    $build->queueGeneration($generate_queue);
    $this->assertEquals(3, $generate_queue->numberOfItems());

    $state = \Drupal::state();
    $deployment_queue = \Drupal::queue('preview_site_deploy:' . $build->id());
    $this->assertEquals(0, $deployment_queue->numberOfItems());
    $this->assertNull($state->get(TestGenerate::COMPLETE_STEP));
    $build->queueDeployment($deployment_queue);
    $this->assertTrue($state->get(TestGenerate::COMPLETE_STEP));
    $this->assertEquals(1, $deployment_queue->numberOfItems());
  }

  /**
   * Tests starting deployment.
   */
  public function testStartingDeployment() {
    $build = $this->createPreviewSiteBuild();
    $state = \Drupal::state();

    $this->assertNull($state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY));
    $this->assertNull($state->get(TestGenerate::PREPARE_STEP));
    $build->startDeployment($state);
    $this->assertTrue($build->get('artifacts')->isEmpty());
    $this->assertTrue($build->get('processed_paths')->isEmpty());
    $this->assertEquals('Starting deployment', $build->log->value);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_BUILDING, $build->getStatus());
    $this->assertEquals($build->uuid(), $state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY));
    $this->assertTrue($state->get(TestGenerate::PREPARE_STEP));
    $this->expectException(GenerationInProgressException::class);
    $build->startDeployment($state);
  }

  /**
   * Tests finishing deployment.
   */
  public function testFinishDeployment() {
    $state = \Drupal::state();
    $file = $this->getTestFile();
    $build = $this->createPreviewSiteBuild([
      'artifacts' => $file->id(),
    ]);
    $time = $this->prophesize(TimeInterface::class);
    $now = time();
    $time->getCurrentTime()->willReturn($now);
    $this->container->set('datetime.time', $time->reveal());
    $return = $build->finishDeployment($state)->getArtifactIds();
    $this->assertEquals([$file->id()], $return);
    $this->assertNull($state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY));
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_BUILT, $build->getStatus());
    $this->assertEquals($now, $build->deployed->value);
    $log_entries = array_column($build->get('log')->getValue(), 'value');
    $this->assertEquals('Finishing deployment', end($log_entries));
  }

  /**
   * Tests finishing deployment that previously failed.
   */
  public function testFinishingDeploymentWhilstFailed() {
    $state = \Drupal::state();
    $build = $this->createPreviewSiteBuild();
    $build->status = PreviewSiteBuildInterface::STATUS_FAILED;
    $build->finishDeployment($state);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_FAILED, $build->getStatus());
  }

  /**
   * Tests deployment failed.
   */
  public function testDeploymentFailed() {
    $state = \Drupal::state();
    $build = $this->createPreviewSiteBuild();
    $build->startDeployment($state);
    $this->assertEquals($build->uuid(), $state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY));
    $build->deploymentFailed($state, TRUE);
    $log_entries = array_column($build->get('log')->getValue(), 'value');
    $this->assertNull($state->get(PreviewSiteBuildInterface::BUILDING_STATE_KEY));
    $this->assertEquals('Deployment failed', end($log_entries));
    $this->assertTrue($build->isFailed());
  }

  /**
   * Tests build contents.
   */
  public function testBuildContents() {
    $vocabulary = $this->createVocabulary();
    $term = $this->createTerm($vocabulary);
    $build = $this->createPreviewSiteBuild([
      'contents' => [$term],
    ]);
    $this->assertEquals([$term->id()], $build->getMatchingContents([$term->id()], 'taxonomy_term'));
    $this->assertEquals([$term->id()], $build->getContentsOfType('taxonomy_term'));
    $this->assertEquals([], $build->getMatchingContents([
      -3,
      -2,
      -1,
    ], 'taxonomy_term'));
  }

  /**
   * Tests build artifacts.
   */
  public function testAddingArtifacts() {
    $file = $this->getTestFile('html', 1);
    $build = $this->createPreviewSiteBuild(['artifacts' => NULL]);
    $build->addArtifact($file);
    $this->assertEquals($file->id(), $build->artifacts->target_id);
  }

  /**
   * Tests keeping track of processed paths.
   */
  public function testProcessedPaths() {
    $build = $this->createPreviewSiteBuild();
    $path = 'some/path';
    $this->assertFalse($build->hasPathBeenProcessed($path));
    $build->markPathAsProcessed($path);
    $this->assertTrue($build->hasPathBeenProcessed($path));
  }

  /**
   * Tests get artifact base-path.
   */
  public function testGetArtifactBasePath() {
    $build = $this->createPreviewSiteBuild();
    $this->assertEquals('public://', $build->getArtifactBasePath());
  }

  /**
   * Tests getExpiryDate.
   */
  public function testGetExpiryDate() {
    $time = new \DateTime('now', new \DateTimeZone('UTC'));
    $build = $this->createPreviewSiteBuild([
      'expiry_date' => $time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ]);
    $this->assertEquals($time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), $build->getExpiryDate()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
  }

  /**
   * Tests decommissioning a preview site build.
   */
  public function testDecomission() {
    $build = $this->createPreviewSiteBuild();
    $state = \Drupal::state();
    $this->assertNull($state->get(TestDeploy::DECOMISSION_STEP));
    $build->decomission();
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_DECOMMISSIONED, $build->getStatus());
    $this->assertTrue($state->get(TestDeploy::DECOMISSION_STEP));
  }

  /**
   * Tests deleting a preview site build.
   */
  public function testDelete() {
    $build = $this->createPreviewSiteBuild();
    $state = \Drupal::state();
    $this->assertNull($state->get(TestDeploy::DELETE_STEP));
    $build->delete();
    $this->assertTrue($state->get(TestDeploy::DELETE_STEP));
    $this->assertNull(PreviewSiteBuild::load($build->id()));
  }

  /**
   * Tests ::getArtifacts.
   */
  public function testGetArtifacts() {
    $files = [
      $this->getTestFile(),
      $this->getTestFile('html', 1),
    ];
    $build = $this->createPreviewSiteBuild([
      'artifacts' => $files,
    ]);
    $artifacts = iterator_to_array($build->getArtifacts());
    $this->assertCount(2, $artifacts);
    $get_uuids = function (FileInterface $file) {
      return $file->uuid();
    };
    $this->assertEquals(array_map($get_uuids, $files), array_map($get_uuids, $artifacts));
  }

  /**
   * Tests ::getItemLinks.
   */
  public function testGetItemLinks() {
    $entity = $this->createTerm($this->createVocabulary());
    $build = $this->createPreviewSiteBuild([
      'contents' => $entity,
      'status' => PreviewSiteBuildInterface::STATUS_BUILT,
    ]);
    $this->assertEquals([
      'item_0' => [
        'title' => $entity->label(),
        'weight' => 0,
        'url' => Url::fromUri($build->getDeploymentBaseUri() . $build->uuid() . $entity->toUrl()->toString() . '/index.html'),
      ],
    ], $build->getItemLinks());
  }

}
