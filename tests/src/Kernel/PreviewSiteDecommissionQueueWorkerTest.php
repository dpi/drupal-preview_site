<?php

namespace src\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site_test\Plugin\PreviewSite\Deploy\TestDeploy;
use Drupal\Tests\preview_site\Kernel\PreviewSiteKernelTestBase;

/**
 * Defines a class for testing the decomission queue worker.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Plugin\QueueWorker\Decommission
 */
class PreviewSiteDecommissionQueueWorkerTest extends PreviewSiteKernelTestBase {

  /**
   * Tests queue worker.
   */
  public function testQueueWorker() {
    $time = new \DateTime('now', new \DateTimeZone('UTC'));
    $time->modify('-1 day');
    $build = $this->createPreviewSiteBuild([
      'expiry_date' => $time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ]);
    $state = \Drupal::state();
    $this->assertNull($state->get(TestDeploy::DECOMISSION_STEP));
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_PENDING, $build->getStatus());
    $this->container->get('cron')->run();
    $build = PreviewSiteBuild::load($build->id());
    $this->assertEquals(PreviewSiteBuildInterface::STATUS_DECOMMISSIONED, $build->getStatus());
    $this->assertTrue($state->get(TestDeploy::DECOMISSION_STEP));
  }

}
