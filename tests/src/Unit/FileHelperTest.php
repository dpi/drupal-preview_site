<?php

namespace Drupal\Tests\preview_site\Unit;

use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Generate\FileHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Defines a test for FileHelper.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Generate\FileHelper
 */
class FileHelperTest extends UnitTestCase {

  /**
   * Tests ::getFilePathWithoutSchema.
   *
   * @dataProvider providerGetFilePathWithoutSchema
   */
  public function testGetFilePathWithoutSchema(string $uri, ?string $base_path, string $expected) : void {
    $file = $this->prophesize(FileInterface::class);
    $build = $this->prophesize(PreviewSiteBuildInterface::class);
    $build->getArtifactBasePath()->willReturn($base_path);
    $file->getFileUri()->willReturn($uri);
    $this->assertEquals($expected, FileHelper::getFilePathWithoutSchema($file->reveal(), $build->reveal()));
  }

  /**
   * Data provider for ::getFilePathWithoutSchema.
   *
   * @return array
   *   Test cases.
   */
  public function providerGetFilePathWithoutSchema() : array {
    return [
      'private' => ['private://some/file.txt', 'private://', 'some/file.txt'],
      'private with prefix' => ['private://some/file.txt', 'private://some', 'file.txt'],
      'private with prefix and /' => ['private://some/file.txt', 'private://some/', 'file.txt'],
      'public' => ['public://some/file.txt', 'public://', 'some/file.txt'],
      'not matching' => ['public://some/file.txt', 'private://', 'public://some/file.txt'],
      'leading /' => ['/some/file.txt', NULL, 'some/file.txt'],
      'no leading /' => ['some/file.txt', NULL, 'some/file.txt'],
    ];
  }

}
