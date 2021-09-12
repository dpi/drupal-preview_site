<?php

namespace Drupal\Tests\preview_site_s3\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\preview_site\Generate\FileHelper;
use Drupal\Tests\preview_site\Kernel\PreviewSiteKernelTestBase;

/**
 * Defines a test for the S3 deploy plugin.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Plugin\PreviewSite\Deploy\PublicFiles
 */
class PublicFilesDeployPluginTest extends PreviewSiteKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'token',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installConfig(['system']);
  }

  /**
   * Tests deploy plugin.
   */
  public function testDeployPlugin() {
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_public',
      'generateSettings' => [],
      'deploySettings' => [
        'naming' => 'preview-site/[preview_site_build:uuid:value]',
      ],
    ]);
    $strategy->save();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'contents' => [EntityTest::create([
        'label' => $this->randomMachineName(),
      ]),
      ],
    ]);
    $files = [];
    foreach ($build->get('artifacts') as $item) {
      $files[] = $item->entity;
    }
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $this->assertDirectoryExists('public://preview-site/' . $build->uuid());
    foreach ($files as $file) {
      $this->assertFileExists(sprintf('public://preview-site/%s/%s', $build->uuid(), FileHelper::getFilePathWithoutSchema($file, $build)));
    }
  }

  /**
   * Tests delete and decomission tasks.
   *
   * @dataProvider providerDeleteAndDecomission
   */
  public function testDeleteAndDecomission(string $operation) {
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_public',
      'generateSettings' => [],
      'deploySettings' => [
        'naming' => 'preview-site/[preview_site_build:uuid:value]',
      ],
    ]);
    $strategy->save();

    $files = [
      $this->getTestFile(),
      $this->getTestFile('html', 1),
    ];
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'contents' => NULL,
      'artifacts' => $files,
    ]);
    foreach ($files as $file) {
      $build->getDeployPlugin()->deployArtifact($build, $file);
    }
    $this->assertDirectoryExists('public://preview-site/' . $build->uuid());

    $build->{$operation}();
    foreach ($build->get('artifacts') as $item) {
      $file = $item->entity;
      $this->assertFileNotExists(sprintf('public://preview-site/%s/%s', $build->uuid(), FileHelper::getFilePathWithoutSchema($file, $build)));
    }
  }

  /**
   * Data provider for ::testDeleteAndDecomission().
   *
   * @return array
   *   Test cases.
   */
  public function providerDeleteAndDecomission() : array {
    return [
      'delete' => ['delete'],
      'decomission' => ['decomission'],
    ];
  }

}
