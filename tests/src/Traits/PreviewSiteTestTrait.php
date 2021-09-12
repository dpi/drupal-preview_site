<?php

namespace Drupal\Tests\preview_site\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\preview_site\Entity\PreviewStrategyInterface;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Defines a trait for testing preview-site functionality.
 *
 * @codeCoverageIgnore
 */
trait PreviewSiteTestTrait {

  use TestFileCreationTrait;

  /**
   * Deployment prefix.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Creates a test strategy.
   *
   * @param string|null $label
   *   Strategy label.
   * @param string|null $id
   *   Strategy ID.
   * @param string|null $prefix
   *   Strategy prefix.
   *
   * @return \Drupal\preview_site\Entity\PreviewStrategyInterface
   *   Preview strategy.
   */
  protected function createStrategy(string $label = NULL, string $id = NULL, string $prefix = NULL) : PreviewStrategyInterface {
    $strategy = PreviewStrategy::create([
      'id' => $id ?: $this->randomMachineName(),
      'label' => $label ?: $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'test',
      'generateSettings' => [],
      'deploySettings' => [
        'prefix' => $prefix ?: $this->randomMachineName(),
      ],
    ]);
    $strategy->save();
    return $strategy;
  }

  /**
   * Gets a test file.
   *
   * @param string $type
   *   File type.
   * @param int $index
   *   Test file index.
   *
   * @return \Drupal\file\FileInterface
   *   Created file.
   */
  protected function getTestFile(string $type = 'html', int $index = 0) : FileInterface {
    $test_files = $this->getTestFiles($type);
    $test_file = $test_files[$index]->uri;
    $file = File::create([
      'uri' => $test_file,
      'status' => \FILE_STATUS_PERMANENT,
      'filename' => basename($test_file),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Creates a preview-site build.
   *
   * @param array $values
   *   Entity values.
   *
   * @return \Drupal\preview_site\Entity\PreviewSiteBuildInterface
   *   New site build.
   */
  protected function createPreviewSiteBuild(array $values = []) : PreviewSiteBuildInterface {
    if (!array_key_exists('strategy', $values)) {
      $values['strategy'] = $this->createStrategy()->id();
    }
    if (!array_key_exists('contents', $values)) {
      $values['contents'] = $this->createUser();
    }
    if (!array_key_exists('artifacts', $values)) {
      $values['artifacts'] = $this->getTestFile()->id();
    }
    $build = PreviewSiteBuild::create($values + [
      'label' => $this->randomMachineName(),
      'log' => 'Created a test item',
      'processed_paths' => ['/' . $this->randomMachineName()],
    ]);
    $build->save();
    return $build;
  }

  /**
   * Gets the URIs of artifacts in the preview-site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   * @param array $files
   *   Array files.
   *
   * @return array
   *   Artifact URIs.
   */
  protected function getArtifactUris(PreviewSiteBuildInterface $build, array $files): array {
    return array_map(function (string $file) use ($build) {
      return str_replace(sprintf('private://preview-site/%s', $build->uuid()), '', $file);
    }, $files);
  }

  /**
   * Gets the filepath for a generated static file for a content-entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   *
   * @return string
   *   Path to file.
   */
  protected function getGeneratedFileForEntity(ContentEntityInterface $entity, PreviewSiteBuildInterface $build) : string {
    return sprintf('public://preview-site-test/%s/%s/%s/index.html', $this->prefix, $build->uuid(), $entity->toUrl()->toString());
  }

}
