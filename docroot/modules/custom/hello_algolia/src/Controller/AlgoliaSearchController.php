<?php

declare(strict_types=1);

namespace Drupal\hello_algolia\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns the Algolia search page.
 */
final class AlgoliaSearchController extends ControllerBase {

  /**
   * Builds the Algolia search page.
   *
   * @return array
   *   A render array for the Algolia search template.
   */
  public function build(): array {
    return [
      '#theme' => 'hello_algolia',
    ];
  }

}
