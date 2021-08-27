<?php

namespace Drupal\preview_site\Generate;

use Drupal\file\FileInterface;

/**
 * Defines a class for a collection of files.
 */
class FileCollection implements \IteratorAggregate {

  /**
   * @var \Drupal\file\FileInterface
   */
  private $files = [];

  /**
   * Constructs a new FileCollection.
   *
   * @param \Drupal\file\FileInterface $files
   *   The files in the collection.
   */
  public function __construct(FileInterface ...$files) {
    $this->files = $files;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->files);
  }

  /**
   * Adds a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   File to add.
   *
   * @return $this
   */
  public function addFile(FileInterface $file) : FileCollection {
    $this->files[] = $file;
    return $this;
  }

}
