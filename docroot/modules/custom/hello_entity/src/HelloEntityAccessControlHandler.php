<?php

declare(strict_types=1);

namespace Drupal\hello_entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the Hello Entity entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class HelloEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view hello_entity'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit hello_entity'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete hello_entity'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete hello_entity revision'),
      'view all revisions', 'view revision' => AccessResult::allowedIfHasPermissions($account, ['view hello_entity revision', 'view hello_entity']),
      'revert' => AccessResult::allowedIfHasPermissions($account, ['revert hello_entity revision', 'edit hello_entity']),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create hello_entity', 'administer hello_entity types'], 'OR');
  }

}
