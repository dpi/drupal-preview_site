<?php

namespace Drupal\preview_site\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\preview_site\Deploy\DeployPluginInterface;
use Drupal\preview_site\Generate\GeneratePluginInterface;
use Drupal\preview_site\Generate\GenerationInProgressException;
use Drupal\preview_site\Plugin\PreviewSitePluginInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines an entity to represent a preview site.
 *
 * @ContentEntityType(
 *   id = "preview_site_build",
 *   label = @Translation("Preview site build"),
 *   label_collection = @Translation("Preview site builds"),
 *   label_singular = @Translation("build"),
 *   label_plural = @Translation("builds"),
 *   label_count = @PluralTranslation(
 *     singular = "@count build",
 *     plural = "@count builds",
 *   ),
 *   bundle_label = @Translation("Strategy"),
 *   handlers = {
 *     "view_builder" = \Drupal\Core\Entity\EntityViewBuilder::class,
 *     "list_builder" = \Drupal\preview_site\EntityHandlers\PreviewSiteBuildListBuilder::class,
 *     "access" = \Drupal\Core\Entity\EntityAccessControlHandler::class,
 *     "views_data" = \Drupal\views\EntityViewsData::class,
 *     "route_provider" = {
 *       "html" = \Drupal\preview_site\EntityHandlers\PreviewSiteBuildRouteProvider::class,
 *     },
 *     "form" = {
 *       "add" = \Drupal\preview_site\Form\PreviewSiteBuildForm::class,
 *       "edit" = \Drupal\preview_site\Form\PreviewSiteBuildForm::class,
 *       "default" = \Drupal\preview_site\Form\PreviewSiteBuildForm::class,
 *       "deploy" = \Drupal\preview_site\Form\PreviewSiteBuildDeployForm::class,
 *       "delete" = \Drupal\Core\Entity\ContentEntityDeleteForm::class
 *     },
 *   },
 *   base_table = "preview_site_build",
 *   revision_table = "preview_site_build_revision",
 *   entity_keys = {
 *     "id" = "bid",
 *     "revision" = "revision_id",
 *     "bundle" = "strategy",
 *     "label" = "label",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   bundle_entity_type = "preview_site_strategy",
 *   field_ui_base_route = "entity.preview_site_strategy.edit_form",
 *   admin_permission = "administer preview_site builds",
 *   links = {
 *     "add-form" = "/admin/structure/preview-site/builds/add/{preview_site_strategy}",
 *     "add-page" = "/admin/structure/preview-site/builds/add",
 *     "edit-form" = "/admin/structure/preview-site/builds/{preview_site_build}/edit",
 *     "canonical" = "/admin/structure/preview-site/builds/{preview_site_build}",
 *     "delete-form" = "/admin/structure/preview-site/builds/{preview_site_build}/delete",
 *     "collection" = "/admin/structure/preview-site/builds",
 *     "deploy-form" = "/admin/structure/preview-site/builds/{preview_site_build}/deploy"
 *   },
 * )
 */
class PreviewSiteBuild extends ContentEntityBase implements PreviewSiteBuildInterface {

  use EntityChangedTrait;
  use RevisionLogEntityTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setSetting('allowed_values', [
        self::STATUS_PENDING => new TranslatableMarkup('Pending'),
        self::STATUS_BUILDING => new TranslatableMarkup('Building'),
        self::STATUS_BUILT => new TranslatableMarkup('Built'),
        self::STATUS_FAILED => new TranslatableMarkup('Failed'),
        self::STATUS_STALE => new TranslatableMarkup('Stale'),
        self::STATUS_DECOMMISSIONED => new TranslatableMarkup('Decommissioned'),
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'list_default',
        'label' => 'above',
        'weight' => -4,
      ])
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_PENDING)
      ->setRevisionable(TRUE)
      ->setDefaultValue(FALSE);

    // Add the revision metadata fields.
    $fields += static::revisionLogBaseFieldDefinitions($entity_type);
    $fields['revision_log_message']->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the build was created.'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['log'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Log'))
      ->setDescription(t('Log entries.'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 40,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['processed_paths'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Processed paths'))
      ->setDescription(t('Processed paths.'))
      ->setSetting('max_length', 512)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 35,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['deployed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Deployed'))
      ->setDescription(t('The time the build was deployed.'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the build was last edited.'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['contents'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel((string) new TranslatableMarkup('Items to build'))
      ->setDescription((string) new TranslatableMarkup('Content entities to build for the preview site.'))
      ->setRequired(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'dynamic_entity_reference_label',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'dynamic_entity_reference_default',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSettings([
        'exclude_entity_types' => TRUE,
        'entity_type_ids' => [
          'preview_site_build',
          'content_moderation_state',
          'monitoring_sensor_result',
          'preview_link',
          'path_alias',
          'scheduled_transition',
          'search_api_task',
          'section_association',
          'redirect',
          'personalised_variant',
        ],
      ]);

    $fields['artifacts'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Artifacts'))
      ->setDescription(t('Preview site build artifacts'))
      ->setSetting('uri_scheme', 'private')
      ->setSetting('file_directory', 'artifacts/[date:custom:Y]-[date:custom:m]')
      ->setSetting('file_extensions', 'css js html png jpeg svg xlsx txt doc docx pdf xls csv')
      ->setSetting('handler', 'default:file')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['expiry_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Expiry date'))
      ->setDescription(t('The date/time the build should be expired.'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 15,
        'type' => 'datetime_default',
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'weight' => 25,
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): ?string {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount(): int {
    return $this->get('contents')->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getStrategyLabel(): string {
    $entity = $this->getStrategy();
    return $entity ? $entity->label() : new TranslatableMarkup('Deleted');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->get('status')->isEmpty()) {
      $this->status = self::STATUS_PENDING;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    \Drupal::service('plugin.manager.queue_worker')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    \Drupal::service('plugin.manager.queue_worker')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getStrategy(): ?PreviewStrategyInterface {
    return $this->get('strategy')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function queueGeneration(QueueInterface $queue) : void {
    foreach ($this->get('contents') as $delta => $item) {
      $queue->createItem($delta);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function queueDeployment(QueueInterface $queue) : void {
    if (($strategy = $this->getStrategy()) && $generate = $strategy->getGeneratePlugin()) {
      $generate->completeBuild($this);
    }
    foreach ($this->get('artifacts') as $delta => $item) {
      $queue->createItem($delta);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function startDeployment(StateInterface $state): array {
    if ($uuid = $state->get(self::BUILDING_STATE_KEY, FALSE)) {
      throw new GenerationInProgressException(sprintf('Preview site ID %s is currently being built, only one site can be built at a time.', $uuid));
    }
    $this->log = NULL;
    $this->processed_paths = NULL;
    $this->artifacts = NULL;
    $this->status = self::STATUS_BUILDING;
    $this->addLogEntry('Starting deployment', FALSE);
    $state->set(self::BUILDING_STATE_KEY, $this->uuid());
    $this->save();
    if (($strategy = $this->getStrategy()) && $generate = $strategy->getGeneratePlugin()) {
      $generate->prepareBuild($this, $this->getDeploymentBaseUri());
    }
    return $this->getArtifactIds();
  }

  /**
   * {@inheritdoc}
   */
  public function finishDeployment(StateInterface $state): PreviewSiteBuildInterface {
    $this->status = $this->isFailed() ? self::STATUS_FAILED : self::STATUS_BUILT;
    $state->delete(self::BUILDING_STATE_KEY);
    $this->deployed = \Drupal::time()->getCurrentTime();
    $this->addLogEntry('Finishing deployment', FALSE);
    $this->save();
    if ($deploy = $this->getDeployPlugin()) {
      $deploy->completeDeployment($this);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getArtifactIds(): array {
    return array_map(function (FileInterface $file) {
      return $file->id();
    }, iterator_to_array($this->getArtifacts()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDeploymentBaseUri(): ?string {
    if ($this->getStatus() === self::STATUS_BUILT && ($strategy = $this->getStrategy())) {
      return $strategy->getDeployPlugin()->getDeploymentBaseUri($this);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deploymentFailed(StateInterface $state, bool $reset = TRUE): void {
    $this->status = self::STATUS_FAILED;
    $this->addLogEntry('Deployment failed', FALSE);
    if ($reset) {
      $state->delete(self::BUILDING_STATE_KEY);
    }
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isFailed(): bool {
    return $this->status->value === self::STATUS_FAILED;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatchingContents(array $ids, string $entity_type_id): array {
    $contents = [];
    foreach ($this->get('contents') as $item) {
      if ($item->target_type === $entity_type_id && in_array($item->target_id, $ids)) {
        $contents[] = $item->target_id;
      }
    }
    return $contents;
  }

  /**
   * {@inheritdoc}
   */
  public function addLogEntry(string $entry, bool $auto_save = TRUE): void {
    $this->log[] = $entry;
    if ($auto_save) {
      $this->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addArtifact(FileInterface $file, bool $auto_save = TRUE): void {
    $this->addLogEntry(sprintf('Added artifact %s', $file->getFilename()), FALSE);
    $this->artifacts[] = $file->id();
    if ($auto_save) {
      $this->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasPathBeenProcessed(string $path): bool {
    return in_array($path, array_column($this->get('processed_paths')->getValue(), 'value'), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function markPathAsProcessed(string $path, bool $auto_save = TRUE): void {
    $this->addLogEntry(sprintf('Marked path %s as processed', $path), FALSE);
    $this->processed_paths[] = $path;
    if ($auto_save) {
      $this->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGeneratePlugin(): ?GeneratePluginInterface {
    if (($strategy = $this->getStrategy()) &&
      ($generate = $strategy->getGeneratePlugin())) {
      return $generate;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentsOfType(string $entity_type_id): array {
    return array_column(array_filter($this->get('contents')->getValue(), function (array $item) use ($entity_type_id) {
      return $entity_type_id === $item['target_type'];
    }), 'target_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getArtifactBasePath(): ?string {
    if ($plugin = $this->getGeneratePlugin()) {
      return $plugin->getArtifactBasePath($this);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiryDate(): ?DrupalDateTime {
    return $this->get('expiry_date')->date;
  }

  /**
   * {@inheritdoc}
   */
  public function decomission(): void {
    if ($deploy = $this->getDeployPlugin()) {
      $deploy->decommissionPreviewSiteBuild($this);
    }
    $this->status = self::STATUS_DECOMMISSIONED;
    $this->addLogEntry('Decommissioned preview site build');
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    if ($deploy = $this->getDeployPlugin()) {
      $deploy->deletePreviewSiteBuild($this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeployPlugin(): ?DeployPluginInterface {
    if (($strategy = $this->getStrategy()) &&
      ($deploy = $strategy->getDeployPlugin())) {
      return $deploy;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getArtifacts(): \Generator {
    foreach ($this->get('artifacts') as $item) {
      assert($item instanceof FileItem);
      if (!$item->entity) {
        continue;
      }
      yield $item->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLinks(): array {
    $links = [];
    $processors = array_filter([
      $this->getGeneratePlugin(),
      $this->getDeployPlugin(),
    ]);
    foreach ($this->get('contents') as $delta => $item) {
      if (!($entity = $item->entity)) {
        continue;
      }
      assert($entity instanceof EntityInterface);
      try {
        $url = $entity->toUrl()->toString();
        foreach ($processors as $processor) {
          assert($processor instanceof PreviewSitePluginInterface);
          $url = $processor->alterUrlToDeployedItem($url, $this);
        }
        $links[sprintf('item_%s', $delta)] = [
          'weight' => $delta,
          'title' => $entity->label(),
          'url' => Url::fromUri($url),
        ];
      }
      catch (\Exception $e) {
        continue;
      }
    }
    return $links;
  }

}
