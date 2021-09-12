<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\PreviewSiteBuilder;
use Drupal\Tests\preview_site\Traits\PreviewSiteTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Defines a base class for preview site tests.
 *
 * @group preview_site
 */
abstract class PreviewSiteKernelTestBase extends KernelTestBase {

  use UserCreationTrait;
  use PreviewSiteTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'preview_site',
    'preview_site_test',
    'text',
    'datetime',
    'file',
    'field',
    'filter',
    'path_alias',
    'options',
    'tome_static',
    'entity_usage',
    'dynamic_entity_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('preview_site_build');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
  }

  /**
   * Generates and deploys a build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build to generate and deploy.
   *
   * @return array
   *   Results.
   */
  protected function genererateAndDeployBuild(PreviewSiteBuildInterface $build) : array {
    $context = [
      'results' => [],
      'finished' => 1,
      'sandbox' => [],
    ];
    $clean_batch = function (array $context) {
      return [
        'finished' => 1,
        'sandbox' => [],
      ] + $context;
    };
    PreviewSiteBuilder::operationMarkDeploymentStarted($build->id(), $context);
    PreviewSiteBuilder::operationQueueGenerate($build->id());
    $context = $clean_batch($context);
    do {
      PreviewSiteBuilder::operationProcessGenerate($build->id(), $context);
    } while ($context['finished'] !== 1);
    $context = $clean_batch($context);
    do {
      PreviewSiteBuilder::operationProcessAssets($build->id(), $context);
    } while ($context['finished'] !== 1);
    PreviewSiteBuilder::operationQueueDeploy($build->id());
    $context = $clean_batch($context);
    do {
      PreviewSiteBuilder::operationProcessDeploy($build->id(), $context);
    } while ($context['finished'] !== 1);
    $files = [];
    // Load back from DB to pass back the artifacts before they're deleted.
    $build = \Drupal::entityTypeManager()->getStorage('preview_site_build')->loadUnchanged($build->id());
    foreach ($build->get('artifacts') as $item) {
      $files[] = $item->entity;
    }
    $context = $clean_batch($context);
    do {
      PreviewSiteBuilder::operationMarkDeploymentFinished($build->id(), $context);
    } while ($context['finished'] !== 1);
    return $context['results'] + ['files' => $files];
  }

}
