<?php

namespace Drupal\preview_site_test\Plugin\PreviewSite\Generate;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Queue\QueueInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Generate\FileCollection;
use Drupal\preview_site\Generate\FileHelper;
use Drupal\preview_site\Generate\GeneratePluginBase;
use Drupal\Tests\TestFileCreationTrait;

// Classes in test namespaces aren't auto-loaded by default. But we don't want
// to duplicate all of the existing useful functionality in that trait.
// @codeCoverageIgnore
require_once DRUPAL_ROOT . '/core/tests/Drupal/Tests/TestFileCreationTrait.php';

/**
 * Defines a test generate plugin.
 *
 * @PreviewSiteGenerate(
 *   id = "test",
 *   title = @Translation("Test"),
 *   description = @Translation("Test generate plugin."),
 * )
 *
 * @codeCoverageIgnore
 */
class TestGenerate extends GeneratePluginBase {

  use TestFileCreationTrait;

  const PREPARE_STEP = 'preview_site_test_generate_prepare';
  const COMPLETE_STEP = 'preview_site_test_generate_complete';

  /**
   * {@inheritdoc}
   */
  public function generateBuildForItem(PreviewSiteBuildInterface $build, EntityReferenceItem $item, string $base_url, QueueInterface $asset_queue): FileCollection {
    $collection = new FileCollection();
    foreach (array_slice($this->getTestFiles('html'), 0, 2) as $file) {
      $collection->addFile(FileHelper::createFromExistingFile($file->uri));
    }
    $asset_queue->createItem('/some/path');
    $asset_queue->createItem('/some/other/path');
    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function generateBuildForPath(PreviewSiteBuildInterface $build, string $path, string $base_url, QueueInterface $asset_queue): FileCollection {
    $collection = new FileCollection();
    if ($build->hasPathBeenProcessed($path)) {
      return $collection;
    }
    foreach (array_slice($this->getTestFiles('html'), 0, 2) as $file) {
      $collection->addFile(FileHelper::createFromExistingFile($file->uri));
    }
    $build->markPathAsProcessed($path);
    $asset_queue->createItem('/some/other/path/still');
    $asset_queue->createItem('/some/other/path/more');
    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareBuild(PreviewSiteBuildInterface $build, ?string $base_url) {
    \Drupal::state()->set(self::PREPARE_STEP, TRUE);
    parent::prepareBuild($build, $base_url);
  }

  /**
   * {@inheritdoc}
   */
  public function completeBuild(PreviewSiteBuildInterface $build) {
    \Drupal::state()->set(self::COMPLETE_STEP, TRUE);
    parent::completeBuild($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getArtifactBasePath(PreviewSiteBuildInterface $build): string {
    return 'public://';
  }

  /**
   * {@inheritdoc}
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string {
    return $build->uuid() . $url . '/index.html';
  }

}
