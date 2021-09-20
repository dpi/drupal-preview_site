<?php

namespace Drupal\Tests\preview_site_s3\Kernel;

use Aws\S3\S3Client;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\preview_site\Generate\FileHelper;
use Drupal\s3client\S3ClientFactoryInterface;
use Drupal\Tests\preview_site\Kernel\PreviewSiteKernelTestBase;
use Prophecy\Argument;

/**
 * Defines a test for the S3 deploy plugin.
 *
 * @group preview_site_s3
 * @covers \Drupal\preview_site_s3\Plugin\PreviewSite\Deploy\S3
 */
class S3DeployPluginTest extends PreviewSiteKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'preview_site_s3',
    's3client',
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
   * Tests S3 deploy plugin.
   */
  public function testS3DeployPlugin() {
    $domain = sprintf('%s.com', $this->randomMachineName());
    $key = $this->randomMachineName();
    $secret = $this->randomMachineName();
    $region = $this->randomMachineName();
    $bucket = $this->randomMachineName();
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_s3',
      'generateSettings' => [],
      'deploySettings' => [
        'bucket' => $bucket,
        'key' => $key,
        'secret' => $secret,
        'region' => $region,
        'naming' => '[preview_site_build:uuid:value]',
        'domain' => '[preview_site_build:uuid:value].' . $domain,
      ],
    ]);
    $strategy->save();
    $client = $this->prophesize(S3Client::class);
    $stack = new \SplStack();
    $client->putObject(Argument::any())->will(function ($args) use ($stack) {
      $stack->push($args);
    });
    $factory = $this->prophesize(S3ClientFactoryInterface::class);
    $factory->createClient($key, $secret, $region)->shouldBeCalled()->willReturn($client->reveal());
    $this->container->set('s3client.factory', $factory->reveal());
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'contents' => [EntityTest::create([
        'label' => $this->randomMachineName(),
      ]),
      ],
    ]);
    $results = $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $this->assertEquals($build->get('artifacts')->count(), $stack->count());
    $first = $stack->top();
    $args = reset($first);
    $this->assertEquals($bucket, $args['Bucket']);
    foreach ($results['files'] as $file) {
      $file_keys[] = sprintf('%s/%s', $build->uuid(), FileHelper::getFilePathWithoutSchema($file, $build));
    }
    $stack->rewind();
    foreach ($stack as $item) {
      $keys[] = $item[0]['Key'];
    }
    $this->assertEquals($file_keys, array_reverse($keys));
    $this->assertStringContainsString(sprintf('%s.%s', $build->uuid(), $domain), $strategy->getDeployPlugin()->getDeploymentBaseUri($build));
  }

  /**
   * Tests S3 deploy plugin with error.
   */
  public function testS3DeployPluginError() {
    $domain = sprintf('https://%s.com', $this->randomMachineName());
    $key = $this->randomMachineName();
    $secret = $this->randomMachineName();
    $region = $this->randomMachineName();
    $bucket = $this->randomMachineName();
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_s3',
      'generateSettings' => [],
      'deploySettings' => [
        'bucket' => $bucket,
        'key' => $key,
        'secret' => $secret,
        'region' => $region,
        'naming' => '[preview_site_build:uuid:value]',
        'domain' => $domain,
      ],
    ]);
    $strategy->save();
    $client = $this->prophesize(S3Client::class);
    $client->putObject(Argument::any())->will(function () {
      throw new \Exception('Whoops');
    });
    $factory = $this->prophesize(S3ClientFactoryInterface::class);
    $factory->createClient($key, $secret, $region)->shouldBeCalled()->willReturn($client->reveal());
    $this->container->set('s3client.factory', $factory->reveal());
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'contents' => [EntityTest::create([
        'label' => $this->randomMachineName(),
      ]),
      ],
    ]);
    $results = $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $logs = array_filter(array_column($build->get('log')->getValue(), 'value'), function ($item) {
      return strpos($item, 'ERROR:') === 0;
    });
    $first_file = reset($results['files']);
    $this->assertEquals(sprintf('ERROR: Could not deploy %s: Whoops', $first_file->getFileUri()), reset($logs));
  }

  /**
   * Tests delete and decomission tasks.
   *
   * @dataProvider providerDeleteAndDecomission
   */
  public function testDeleteAndDecomission(string $operation) {
    $domain = sprintf('https://%s.com', $this->randomMachineName());
    $key = $this->randomMachineName();
    $secret = $this->randomMachineName();
    $region = $this->randomMachineName();
    $bucket = $this->randomMachineName();
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_s3',
      'generateSettings' => [],
      'deploySettings' => [
        'bucket' => $bucket,
        'key' => $key,
        'secret' => $secret,
        'region' => $region,
        'naming' => '[preview_site_build:uuid:value]',
        'domain' => $domain,
      ],
    ]);
    $stack = new \SplStack();
    $strategy->save();
    $file1 = $this->getTestFile();
    $file2 = $this->getTestFile('html', 1);
    $client = $this->prophesize(S3Client::class);
    $uuid = \Drupal::service('uuid')->generate();
    $file_keys = [
      ['Key' => sprintf('%s/%s', $uuid, $file1->getFilename())],
      ['Key' => sprintf('%s/%s', $uuid, $file2->getFilename())],
    ];
    $client->getIterator('ListObjects', [
      'Bucket' => $bucket,
      'Prefix' => $uuid,
    ])->shouldBeCalled()->willReturn($file_keys);
    $client->deleteObjects(Argument::any())->will(function ($args) use ($stack) {
      $stack->push($args);
    })->shouldBeCalled();
    $factory = $this->prophesize(S3ClientFactoryInterface::class);
    $factory->createClient($key, $secret, $region)->shouldBeCalled()->willReturn($client->reveal());
    $this->container->set('s3client.factory', $factory->reveal());
    $build = $this->createPreviewSiteBuild([
      'uuid' => $uuid,
      'strategy' => $strategy->id(),
      'contents' => NULL,
      'artifacts' => [],
    ]);
    $build->save();
    $build->{$operation}();
    $this->assertEquals(1, $stack->count());
    $first = $stack->top();
    $args = reset($first);
    $this->assertEquals($bucket, $args['Bucket']);
    $this->assertEquals($file_keys, $args['Delete']['Objects']);
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

  /**
   * Tests ::alterUrlToDeployedItem().
   */
  public function testAlterUrlToDeployedItem(): void {
    $domain = sprintf('%s.com', $this->randomMachineName());
    $key = $this->randomMachineName();
    $secret = $this->randomMachineName();
    $region = $this->randomMachineName();
    $bucket = $this->randomMachineName();
    $strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'test',
      'deploy' => 'preview_site_s3',
      'generateSettings' => [],
      'deploySettings' => [
        'bucket' => $bucket,
        'key' => $key,
        'secret' => $secret,
        'region' => $region,
        'naming' => '[preview_site_build:uuid:value]',
        'domain' => '[preview_site_build:uuid:value].' . $domain,
      ],
    ]);
    $strategy->save();
    $entity = EntityTest::create([
      'label' => $this->randomMachineName(),
    ]);
    $entity->save();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $strategy->id(),
      'contents' => $entity,
    ]);
    $build->save();
    $this->assertEquals('https://' . $build->uuid() . '.' . $domain . '/' . trim($entity->toUrl()->toString(), '/'), $build->getDeployPlugin()->alterUrlToDeployedItem($entity->toUrl()->toString(), $build));
  }

}
