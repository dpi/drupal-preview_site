<?php

namespace Drupal\preview_site\Generate;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;

/**
 * Defines a class for writing file contents to file entities.
 */
class FileHelper {

  /**
   * {@inheritdoc}
   */
  public static function createFromExistingFile(string $file_path): FileInterface {
    if (!file_exists($file_path)) {
      throw new CouldNotWriteFileException(sprintf('File: %s does not exist', $file_path));
    }
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => $file_path,
      'status' => \FILE_STATUS_PERMANENT,
      'filename' => basename($file_path),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Gets the file path without the schema.
   *
   * @param \Drupal\file\FileInterface $file
   *   File to get file path for.
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build interface.
   *
   * @return string
   *   File path without the schema and any leading or trailing /.
   */
  public static function getFilePathWithoutSchema(FileInterface $file, PreviewSiteBuildInterface $build) : string {
    $uri = $file->getFileUri();
    if ($base_path = $build->getArtifactBasePath()) {
      return trim(str_replace($base_path, '', $uri), '/');
    }
    return trim($uri, '/');
  }

}
