<?php

declare(strict_types=1);

namespace Drupal\hello_entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Hello Entity entity type.
 */
interface HelloEntityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
