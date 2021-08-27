<?php

namespace Drupal\preview_site\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for editing preview site build entities.
 *
 * @codeCoverageIgnore
 * @see \Drupal\Tests\preview_site\Functional\PreviewSiteBuildAdministrationTest
 */
class PreviewSiteBuildForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $build = $this->entity;

    $result = $build->save();

    $edit_link = $build->toLink($this->t('Edit'), 'edit-form')->toString();
    $view_link = $build->toLink()->toString();
    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created new preview site build %preview_site_build.', ['%preview_site_build' => $view_link]));
        $this->logger('preview_site')->notice('Created new preview site build %preview_site_build.', ['%preview_site_build' => $build->label(), 'link' => $edit_link]);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('Updated preview site build %preview_site_build.', ['%preview_site_build' => $view_link]));
        $this->logger('preview_site')->notice('Updated preview site build %preview_site_build.', ['%preview_site_build' => $build->label(), 'link' => $edit_link]);
        break;
    }
    $form_state->setRedirectUrl($build->toUrl('collection'));
  }

}
