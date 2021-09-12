<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a class for testing the tome generator.
 *
 * @group preview_site
 *
 * @covers \Drupal\preview_site\Plugin\PreviewSite\Generate\TomeGenerator
 * @covers \Drupal\preview_site\Generate\TomeStaticExtension
 */
class TomeGeneratorNodeGlobalTest extends TomeGeneratorTestBase {

  /**
   * Tests get artifact base path.
   */
  public function testGetArtifactBasePath() {
    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => NULL,
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->assertEquals('private://preview-site/' . $build->uuid(), $build->getArtifactBasePath());
  }

  /**
   * Tests generation.
   */
  public function testGenerationDefault() {
    $published_text = $this->getRandomGenerator()->sentences(10);
    $alias = $this->randomMachineName();
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'path' => ['alias' => '/' . $alias],
      'body' => [
        'format' => 'plain_text',
        'value' => $published_text,
      ],
    ]);
    $node->save();
    $draft_text = $this->getRandomGenerator()->sentences(10);
    $node->body->value = $draft_text;
    $node->moderation_state = 'draft';
    $node->setNewRevision(TRUE);
    $node->save();

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $results = $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $draft_text))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $published_text))->count());

    $css_file = $crawler->filter('link[rel=stylesheet]')->first()->attr('href');
    $artifacts_uris = $this->getArtifactUris($build, array_map(function (FileInterface $file) {
      return $file->getFileUri();
    }, $results['files']));
    $this->assertContains(parse_url($css_file, PHP_URL_PATH), $artifacts_uris, var_export($artifacts_uris, TRUE));

    // Test that regeneration is possible.
    $new_draft_text = $this->getRandomGenerator()->sentences(10);
    $node->body->value = $new_draft_text;
    $node->moderation_state = 'draft';
    $node->setNewRevision(TRUE);
    $node->save();
    $this->genererateAndDeployBuild($build);
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $new_draft_text))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $published_text))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $draft_text))->count());
  }

  /**
   * Tests generation, with blocks based on content-type.
   */
  public function testGenerationWithBlockContentTypeVisibility() {
    $this->placeBlock('system_powered_by_block', [
      'region' => 'content',
      'visibility' => [
        'node_type' => [
          'id' => 'node_type',
          'bundles' => [
            'page' => 'page',
          ],
          'negate' => FALSE,
          'context_mapping' => [
            'node' => '@node.node_route_context:node',
          ],
        ],
      ],
    ]);
    $body = $this->getRandomGenerator()->sentences(10);
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $body,
      ],
    ]);
    $node->save();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $body))->count());
    $this->assertGreaterThan(0, $crawler->filter('span:contains("Powered by")')->count());
  }

  /**
   * Tests generation, with blocks based on path.
   */
  public function testGenerationWithBlockPathVisibility() {
    $body = $this->getRandomGenerator()->sentences(10);
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $body,
      ],
    ]);
    $node->save();
    $this->placeBlock('system_powered_by_block', [
      'region' => 'content',
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'pages' => '/' . $node->toUrl()->getInternalPath(),
          'negate' => FALSE,
          'context_mapping' => [],
        ],
      ],
    ]);
    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $body))->count());
    $this->assertGreaterThan(0, $crawler->filter('span:contains("Powered by")')->count());
  }

  /**
   * Tests can recover from exceptions.
   */
  public function testExceptionDuringGeneration() {
    $published_text = $this->getRandomGenerator()->sentences(10);
    $alias = $this->randomMachineName();
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'path' => ['alias' => '/' . $alias],
      'body' => [
        'format' => 'plain_text',
        'value' => $published_text,
      ],
    ]);

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->container->get('event_dispatcher')->addListener(TomeStaticEvents::PATH_PLACEHOLDER, function () {
      throw new \Exception();
    }, 1000);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $log = array_column($build->get('log')->getValue(), 'value');
    $this->assertContains('Deployment failed', $log);
    $log = array_filter($log, function ($item) {
      return strpos($item, 'ERROR:') === 0;
    });
    $this->assertStringContainsString('ERROR: Exception caught when requesting path', end($log));
  }

  /**
   * Tests can recover from invalid paths.
   */
  public function testInvalidPath() {
    $published_text = $this->getRandomGenerator()->sentences(10);
    $alias = $this->randomMachineName();
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'path' => ['alias' => '/' . $alias],
      'body' => [
        'format' => 'plain_text',
        'value' => $published_text,
      ],
    ]);

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->container->get('event_dispatcher')->addListener(TomeStaticEvents::PATH_PLACEHOLDER, function (PathPlaceholderEvent $event) {
      $event->setInvalid();
    }, 1000);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $log = array_column($build->get('log')->getValue(), 'value');
    $log = array_filter($log, function ($item) {
      return strpos($item, 'WARNING:') === 0;
    });
    $this->assertStringContainsString('WARNING: The anonymous user does not have access to view the latest revision for', end($log));
  }

}
