<?php

declare(strict_types=1);

namespace Drupal\hello_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hello_entity\Form\HelloEntityTypeForm;
use Drupal\hello_entity\HelloEntityTypeListBuilder;

/**
 * Defines the Hello Entity type configuration entity.
 */
#[ConfigEntityType(
  id: 'hello_entity_type',
  label: new TranslatableMarkup('Hello Entity type'),
  label_collection: new TranslatableMarkup('Hello Entity types'),
  label_singular: new TranslatableMarkup('Hello Entity type'),
  label_plural: new TranslatableMarkup('Hello Entity types'),
  config_prefix: 'hello_entity_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => HelloEntityTypeListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => HelloEntityTypeForm::class,
      'edit' => HelloEntityTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/hello_entity_types/add',
    'edit-form' => '/admin/structure/hello_entity_types/manage/{hello_entity_type}',
    'delete-form' => '/admin/structure/hello_entity_types/manage/{hello_entity_type}/delete',
    'collection' => '/admin/structure/hello_entity_types',
  ],
  admin_permission: 'administer hello_entity types',
  bundle_of: 'hello_entity',
  label_count: [
    'singular' => '@count Hello Entity type',
    'plural' => '@count Hello Entity types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
  ],
)]
final class HelloEntityType extends ConfigEntityBundleBase {

  /**
   * The machine name of this Hello Entity type.
   */
  protected string $id;

  /**
   * The human-readable name of the Hello Entity type.
   */
  protected string $label;

}
