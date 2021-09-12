<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\preview_site\PreviewSiteCacheContext;

/**
 * Defines a class for testing the cache context.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\PreviewSiteCacheContext
 */
class PreviewSiteCacheContextTest extends PreviewSiteKernelTestBase {

  /**
   * Tests cache context.
   */
  public function testCacheContext() {
    $context = \Drupal::service('cache_context.preview_site');
    $this->assertEquals(PreviewSiteCacheContext::NO_ACTIVE_PREVIEW_SITE_GENERATION, $context->getContext());
    $this->assertEquals('Preview site', (string) PreviewSiteCacheContext::getLabel());
    $state = \Drupal::state();
    $build = $this->createPreviewSiteBuild();
    $build->startDeployment($state);
    $this->assertEquals($build->id(), $context->getContext());
    $build->deploymentFailed($state);
    $this->assertEquals(PreviewSiteCacheContext::NO_ACTIVE_PREVIEW_SITE_GENERATION, $context->getContext());
  }

  /**
   * Tests cache metadata.
   */
  public function testCacheMetadata() {
    $context = \Drupal::service('cache_context.preview_site');
    $this->assertEquals(['preview_site_build_list'], $context->getCacheableMetadata()->getCacheTags());
    $build = $this->createPreviewSiteBuild();
    $build->startDeployment(\Drupal::state());
    $expected = array_unique(array_merge($build->getCacheTags(), ['preview_site_build_list']));
    sort($expected);
    $actual = $context->getCacheableMetadata()->getCacheTags();
    sort($actual);
    $this->assertEquals($expected, $actual);
  }

}
