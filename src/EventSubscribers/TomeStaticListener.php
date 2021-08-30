<?php

namespace Drupal\preview_site\EventSubscribers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\EventSubscriber\EntityPathSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\tome_static\Event\FileSavedEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Defines an event listener for Tome's file-saved event.
 */
class TomeStaticListener implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TomeStaticListener.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Destination of the last file written.
   *
   * @var string
   */
  protected $lastFileWritten;

  /**
   * Event listener for the TomeStaticEvents::FILE_SAVED event.
   *
   * @param \Drupal\tome_static\Event\FileSavedEvent $event
   *   Event.
   *
   * @see ::create
   */
  public function onTomeFileSaved(FileSavedEvent $event) {
    $this->lastFileWritten = $event->getPath();
  }

  /**
   * Reacts to a path placeholder event.
   *
   * This differs from \Drupal\tome_static\EventSubscriber\EntityPathSubscriber
   * in that it does access checking on the default revision, instead of the
   * loaded revision.
   *
   * @param \Drupal\tome_static\Event\PathPlaceholderEvent $event
   *   The path placeholder event.
   */
  public function replacePathPlaceholder(PathPlaceholderEvent $event) {
    $path = $event->getPath();
    if (strpos($path, EntityPathSubscriber::PLACEHOLDER_PREFIX . ':') === 0) {
      [, $entity_type_id, , $entity_id] = explode(':', $path);
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!($entity_storage instanceof RevisionableStorageInterface) ||
        !$entity_type->getRevisionDataTable() ||
        ($entity_type->getLinkTemplate('edit-form') === $entity_type->getLinkTemplate('canonical'))) {
        // This entity doesn't have revisions, we defer this to the version in
        // Tome.
        return;
      }
      $result = $entity_storage->getQuery()
        ->currentRevision()
        ->condition($entity_type->getKey('id'), $entity_id)
        ->accessCheck(FALSE)
        ->execute();
      if ($result &&
        ($default_revision_id = key($result)) &&
        ($default_revision = $entity_storage->loadRevision($default_revision_id))) {
        $event->stopPropagation();
        $url = $default_revision->toUrl('canonical');
        if (!$default_revision->access('view')) {
          $event->setInvalid();
          return;
        }
        $event->setPath(parse_url($url->toString(), PHP_URL_PATH));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TomeStaticEvents::FILE_SAVED => ['onTomeFileSaved'],
      // We have a priority of 100 to run before
      // \Drupal\tome_static\EventSubscriber\EntityPathSubscriber.
      TomeStaticEvents::PATH_PLACEHOLDER => ['replacePathPlaceholder', 100],
    ];
  }

  /**
   * Gets value of LastFileWritten.
   *
   * @return string|null
   *   Value of LastFileWritten.
   */
  public function getLastFileWritten(): ?string {
    return $this->lastFileWritten;
  }

  /**
   * Resets the last file written.
   */
  public function resetLastFileWritten() : void {
    $this->lastFileWritten = NULL;
  }

}
