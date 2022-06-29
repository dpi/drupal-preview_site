<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\QueueWorker\ProcessCopiedFiles;
use Drupal\preview_site\PreviewSiteBuilder;
use Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate;
use Drupal\Tests\preview_site\Traits\LoggingTrait;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Defines a test for preview site builder.
 *
 * @group preview_site
 *
 * @covers \Drupal\preview_site\PreviewSiteBuilder
 * @covers \Drupal\preview_site\Plugin\QueueWorker\Generate
 * @covers \Drupal\preview_site\Plugin\QueueWorker\Deploy
 * @covers \Drupal\preview_site\Plugin\QueueWorker\GenerateAssets
 */
class PreviewSiteBuilderTest extends PreviewSiteKernelTestBase {

  use LoggingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests getting the running build.
   */
  public function testGetRunningBuild() {
    $build = $this->createPreviewSiteBuild();
    $preview_site_builder = PreviewSiteBuilder::factory();
    $this->assertNull($preview_site_builder->getRunningBuild());
    $build->startDeployment(\Drupal::state());
    $this->assertEquals($build->uuid(), $preview_site_builder->getRunningBuild()->uuid());
  }

  /**
   * Tests queuing site generation.
   */
  public function testQueueSiteGeneration() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
        EntityTest::create(),
        EntityTest::create(),
      ],
    ]);
    $asset_queue = \Drupal::queue('preview_site_assets:' . $build->id());
    $asset_queue->createItem(['dummy' => TRUE]);
    $this->assertEquals(1, $asset_queue->numberOfItems());
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $generate_queue->createItem(['dummy' => TRUE]);
    $this->assertEquals(1, $generate_queue->numberOfItems());
    PreviewSiteBuilder::factory()->queueSiteGeneration($build);
    // There should be 3 items in the queue (3 entity test entities).
    $this->assertEquals(3, $generate_queue->numberOfItems());
  }

  /**
   * Tests processing site generation.
   */
  public function testProcessSiteGeneration() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
        EntityTest::create(),
        EntityTest::create(),
      ],
      'artifacts' => NULL,
    ]);
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $this->assertEquals(0, $generate_queue->numberOfItems());
    $this->assertTrue($build->get('artifacts')->isEmpty());
    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->queueSiteGeneration($build);
    // There should be 3 items in the queue (3 entity test entities).
    $this->assertEquals(3, $generate_queue->numberOfItems());
    // Processing an item should reduce the count.
    $this->assertEquals(2, $preview_site_builder->processSiteGeneration($build));
    // And there should be artifacts.
    $this->assertFalse(PreviewSiteBuild::load($build->id())->get('artifacts')->isEmpty());
  }

  /**
   * Test processing an empty generation queue.
   */
  public function testProcessingAnEmptySiteGenerationQueue() {
    $build = $this->createPreviewSiteBuild([
      'contents' => NULL,
      'artifacts' => NULL,
    ]);
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $this->assertEquals(0, $generate_queue->numberOfItems());
    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->queueSiteGeneration($build);
    $this->assertEquals(0, $preview_site_builder->processSiteGeneration($build));
  }

  /**
   * Tests processing asset generation.
   */
  public function testProcessAssetGeneration() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
      ],
      'artifacts' => NULL,
    ]);
    $asset_queue = \Drupal::queue('preview_site_assets:' . $build->id());
    $this->assertEquals(0, $asset_queue->numberOfItems());
    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->queueSiteGeneration($build);
    // This will populate the asset queue.
    // @see \Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate::generateBuildForItem
    $preview_site_builder->processSiteGeneration($build);
    $this->assertEquals(2, $asset_queue->numberOfItems());
    // Processing an item adds two more.
    // @see \Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate::generateBuildForPath
    $this->assertEquals(3, $preview_site_builder->processAssetGeneration($build));
    $this->assertEquals(3, $asset_queue->numberOfItems());
  }

  /**
   * @covers \Drupal\preview_site\PreviewSiteBuilder::processFileCopies
   * @covers \Drupal\preview_site\Plugin\QueueWorker\ProcessCopiedFiles
   */
  public function testProcessFileCopies(): void {
    vfsStream::setup('test', NULL, [
      'myfile.html' => '<html><body>foo bar</body></html>',
    ]);

    $build = $this->createPreviewSiteBuild([
      'contents' => [EntityTest::create()],
      'artifacts' => NULL,
    ]);
    $queue = \Drupal::queue(sprintf('%s:%s', ProcessCopiedFiles::PLUGIN_ID, $build->id()));
    $queue->createItem(ProcessCopiedFiles::createItem('vfs://test/myfile.html', 'vfs://test/destination.html'));

    $preview_site_builder = PreviewSiteBuilder::factory();
    $this->assertFalse(file_exists('vfs://test/destination.html'));
    $this->assertCount(0, iterator_to_array($build->getArtifacts()));
    $this->assertFalse($build->hasPathBeenProcessed('vfs://test/myfile.html'));

    $preview_site_builder->processFileCopies($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertTrue(file_exists('vfs://test/destination.html'));
    $this->assertEquals('<html><body>foo bar</body></html>', \file_get_contents('vfs://test/destination.html'));
    $this->assertTrue($build->hasPathBeenProcessed('vfs://test/destination.html'));
    $artifact0 = iterator_to_array($build->getArtifacts())[0];
    $this->assertInstanceOf(FileInterface::class, $artifact0);
    $this->assertEquals('destination.html', $artifact0->label());
  }

  /**
   * @covers \Drupal\preview_site\Plugin\QueueWorker\ProcessCopiedFiles
   */
  public function testProcessFileCopiesDirectoryCreateFailure(): void {
    vfsStream::setup('test', 0, [
      'myfile.html' => '<html><body>foo bar</body></html>',
    ]);

    $build = $this->createPreviewSiteBuild([
      'contents' => [EntityTest::create()],
      'artifacts' => NULL,
    ]);
    $queue = \Drupal::queue(sprintf('%s:%s', ProcessCopiedFiles::PLUGIN_ID, $build->id()));
    $queue->createItem(ProcessCopiedFiles::createItem('vfs://test/myfile.html', 'vfs://test/destination.html'));

    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->processFileCopies($build);

    $logs = $this->getLogs();
    $this->assertEquals('Unable to prepare directory vfs://test for writing', $logs[0]['message']);
    $this->assertEquals(RfcLogLevel::NOTICE, $logs[0]['severity']);
  }

  /**
   * @covers \Drupal\preview_site\Plugin\QueueWorker\ProcessCopiedFiles
   */
  public function testProcessFileCopiesDirectoryFileCopyFailure(): void {
    vfsStream::setup('test', NULL, [
      'myfile.html' => '<html><body>foo bar</body></html>',
    ]);

    $build = $this->createPreviewSiteBuild([
      'contents' => [EntityTest::create()],
      'artifacts' => NULL,
    ]);
    $queue = \Drupal::queue(sprintf('%s:%s', ProcessCopiedFiles::PLUGIN_ID, $build->id()));
    $queue->createItem(ProcessCopiedFiles::createItem('vfs://test/file-does-not-exist.html', 'vfs://test/destination.html'));

    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->processFileCopies($build);

    $logs = $this->getLogs();
    $this->assertEquals("File 'vfs://test/file-does-not-exist.html' could not be copied because it does not exist.", $logs[0]['message']);
    $this->assertEquals(RfcLogLevel::ERROR, $logs[0]['severity']);
  }

  /**
   * Tests queueing the site deployment.
   */
  public function testQueueSiteDeployment() {
    $build = $this->createPreviewSiteBuild([
      'contents' => NULL,
      'artifacts' => [
        $this->getTestFile(),
        $this->getTestFile('image'),
      ],
    ]);
    $deploy_queue = \Drupal::queue('preview_site_deploy:' . $build->id());
    $deploy_queue->createItem(['dummy' => TRUE]);
    $this->assertEquals(1, $deploy_queue->numberOfItems());
    $state = \Drupal::state();
    $this->assertNull($state->get(TestGenerate::COMPLETE_STEP));
    PreviewSiteBuilder::factory()->queueSiteDeployment($build);
    $this->assertEquals(2, $deploy_queue->numberOfItems());
    $this->assertTrue($state->get(TestGenerate::COMPLETE_STEP));
    $items = [];
    while ($item = $deploy_queue->claimItem()) {
      $items[] = $item->data;
    }
    $this->assertEquals([0, 1], $items);
  }

  /**
   * Tests processing the site deployment.
   */
  public function testProcessSiteDeployment() {
    $file = $this->getTestFile();
    $build = $this->createPreviewSiteBuild([
      'contents' => NULL,
      'artifacts' => [
        $file,
        $this->getTestFile('image'),
      ],
    ]);
    $deploy_queue = \Drupal::queue('preview_site_deploy:' . $build->id());
    $preview_site_builder = PreviewSiteBuilder::factory();
    $preview_site_builder->queueSiteDeployment($build);
    $this->assertEquals(2, $deploy_queue->numberOfItems());
    $this->assertFalse(file_exists('public://preview-site-test/' . $file->getFilename()));
    $this->assertEquals(1, $preview_site_builder->processSiteDeployment($build));
    $this->assertEquals(1, $deploy_queue->numberOfItems());
    $this->assertTrue(file_exists('public://preview-site-test/' . parse_url($file->getFileUri(), \PHP_URL_PATH)));
  }

  /**
   * Tests batch building.
   */
  public function testPreviewSiteBuildBatch() {
    $build = $this->createPreviewSiteBuild();
    $batch = PreviewSiteBuilder::factory()->getPreviewSiteBuildBatch($build)->toArray();
    $this->assertEquals([
      [[PreviewSiteBuilder::class, 'operationMarkDeploymentStarted'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationQueueGenerate'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationProcessGenerate'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationProcessAssets'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationProcessFileCopies'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationQueueDeploy'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationProcessDeploy'], [$build->id()]],
      [[PreviewSiteBuilder::class, 'operationMarkDeploymentFinished'], [$build->id()]],
    ], $batch['operations']);
  }

  /**
   * Tests mark deployment started operation.
   */
  public function testOperationMarkDeploymentStarted() {
    $build = $this->createPreviewSiteBuild();
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    PreviewSiteBuilder::operationMarkDeploymentStarted($build->id(), $context);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_BUILDING, $build->getStatus());
    $this->assertEquals($build->id(), $context['results']['build_id']);
    $this->assertEquals('Marked deployment as building', (string) $context['message']);
  }

  /**
   * Tests operation queue generate.
   */
  public function testOperationQueueGenerate() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
        EntityTest::create(),
        EntityTest::create(),
      ],
    ]);
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $this->assertEquals(0, $generate_queue->numberOfItems());
    PreviewSiteBuilder::operationQueueGenerate($build->id());
    $this->assertEquals(3, $generate_queue->numberOfItems());
  }

  /**
   * Tests operation process generate.
   */
  public function testOperationProcessGenerate() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
        EntityTest::create(),
        EntityTest::create(),
      ],
      'artifacts' => NULL,
    ]);
    $generate_queue = \Drupal::queue('preview_site_generate:' . $build->id());
    $this->assertEquals(0, $generate_queue->numberOfItems());
    PreviewSiteBuilder::operationQueueGenerate($build->id());
    $this->assertEquals(3, $generate_queue->numberOfItems());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    PreviewSiteBuilder::operationProcessGenerate($build->id(), $context);
    $this->assertEquals(2, $generate_queue->numberOfItems());
    $this->assertEquals(1 / 3, $context['finished']);
    $this->assertEquals(1, $context['results']['generated']);
    $this->assertFalse(PreviewSiteBuild::load($build->id())->get('artifacts')->isEmpty());
  }

  /**
   * Tests processing asset generation.
   */
  public function testOperationProcessAssets() {
    $build = $this->createPreviewSiteBuild([
      'contents' => [
        EntityTest::create(),
      ],
      'artifacts' => NULL,
    ]);
    $asset_queue = \Drupal::queue('preview_site_assets:' . $build->id());
    $this->assertEquals(0, $asset_queue->numberOfItems());
    PreviewSiteBuilder::operationQueueGenerate($build->id());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    $this->assertEquals(0, $asset_queue->numberOfItems());
    PreviewSiteBuilder::operationProcessGenerate($build->id(), $context);
    // @see \Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate::generateBuildForItem
    $this->assertEquals(2, $asset_queue->numberOfItems());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    $count = PreviewSiteBuild::load($build->id())->get('artifacts')->count();
    PreviewSiteBuilder::operationProcessAssets($build->id(), $context);
    // @see \Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate::generateBuildForPath
    $this->assertEquals(3, $asset_queue->numberOfItems());
    $this->assertEquals(1 / 4, $context['finished']);
    $this->assertEquals(1, $context['results']['assets']);
    // @see \Drupal\preview_site_test\Plugin\PreviewSite\Generate\TestGenerate::generateBuildForPath
    $this->assertEquals($count + 2, PreviewSiteBuild::load($build->id())->get('artifacts')->count());
  }

  /**
   * Tests queueing the site deployment.
   */
  public function testOperationQueueDeployment() {
    $build = $this->createPreviewSiteBuild([
      'contents' => NULL,
      'artifacts' => [
        $this->getTestFile(),
        $this->getTestFile('image'),
      ],
    ]);
    $deploy_queue = \Drupal::queue('preview_site_deploy:' . $build->id());
    $this->assertEquals(0, $deploy_queue->numberOfItems());
    $state = \Drupal::state();
    $this->assertNull($state->get(TestGenerate::COMPLETE_STEP));
    PreviewSiteBuilder::operationQueueDeploy($build->id());
    $this->assertEquals(2, $deploy_queue->numberOfItems());
    $this->assertTrue($state->get(TestGenerate::COMPLETE_STEP));
  }

  /**
   * Tests processing the site deployment.
   */
  public function testOperationProcessDeployment() {
    $file = $this->getTestFile();
    $build = $this->createPreviewSiteBuild([
      'contents' => NULL,
      'artifacts' => [
        $file,
        $this->getTestFile('image'),
      ],
    ]);
    $deploy_queue = \Drupal::queue('preview_site_deploy:' . $build->id());
    PreviewSiteBuilder::operationQueueDeploy($build->id());
    $this->assertEquals(2, $deploy_queue->numberOfItems());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    $this->assertFalse(file_exists('public://preview-site-test/' . $file->getFilename()));
    PreviewSiteBuilder::operationProcessDeploy($build->id(), $context);
    $this->assertEquals(1, $deploy_queue->numberOfItems());
    $this->assertEquals(1 / 2, $context['finished']);
    $this->assertEquals(1, $context['results']['deployed']);
    $this->assertTrue(file_exists('public://preview-site-test/' . parse_url($file->getFileUri(), \PHP_URL_PATH)));
  }

  /**
   * Tests operation mark deployment finished.
   */
  public function testOperationMarkDeploymentFinished() {
    $file1 = $this->getTestFile();
    $file2 = $this->getTestFile('image');
    $build = $this->createPreviewSiteBuild([
      'artifacts' => [
        $file1,
        $file2,
      ],
    ]);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_PENDING, $build->getStatus());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    PreviewSiteBuilder::operationMarkDeploymentFinished($build->id(), $context);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_BUILT, PreviewSiteBuild::load($build->id())->getStatus());
    $this->assertEquals(1, $context['finished']);
    $this->assertEquals(2, $context['sandbox']['total']);
    $this->assertTrue(empty($context['results']['generate_errors']));
  }

  /**
   * Tests operation mark deployment finished with failure.
   */
  public function testOperationMarkDeploymentFinishedWithFailure() {
    $build = $this->createPreviewSiteBuild([
      'status' => PreviewSiteBuildInterface::STATUS_FAILED,
    ]);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_FAILED, $build->getStatus());
    $context = [
      'finished' => 1,
      'sandbox' => [],
    ];
    PreviewSiteBuilder::operationMarkDeploymentFinished($build->id(), $context);
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_FAILED, PreviewSiteBuild::load($build->id())->getStatus());
    $this->assertFalse(empty($context['results']['generate_errors']));
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container
      ->register($this->testLoggerServiceName, BufferingLogger::class)
      ->addTag('logger');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean out logs so their arn't sent out to stderr.
    $this->container->get($this->testLoggerServiceName)->cleanLogs();
    parent::tearDown();
  }

}
