<?php

declare(strict_types=1);

namespace Drupal\hello_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides primary navigation menu items as a REST resource.
 */
#[RestResource(
  id: 'primary_navigation',
  label: new TranslatableMarkup('Primary Navigation'),
  uri_paths: [
    'canonical' => '/api/primary-navigation',
  ],
)]
final class PrimaryNavigationResource extends ResourceBase {

  /**
   * The menu link tree service.
   */
  private readonly MenuLinkTreeInterface $menuLinkTree;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    MenuLinkTreeInterface $menuLinkTree,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->menuLinkTree = $menuLinkTree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('menu.link_tree'),
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponse {
    $parameters = (new MenuTreeParameters())
      ->onlyEnabledLinks();

    $tree = $this->menuLinkTree->load('main', $parameters);
    $tree = $this->menuLinkTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $data = $this->normalizeTree($tree);

    $resource = [
      'result_count' => count($data),
      'data' => $data,
    ];

    $response = new ResourceResponse($resource);
    $response->addCacheableDependency(
      (new CacheableMetadata())
        ->setCacheTags(['config:system.menu.main'])
    );

    return $response;
  }

  /**
   * Converts a Drupal menu tree into API-friendly nested data.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree elements.
   *
   * @return array<int, array<string, mixed>>
   *   The normalized menu items.
   */
  private function normalizeTree(array $tree): array {
    $items = [];

    foreach ($tree as $element) {
      if (!$element instanceof MenuLinkTreeElement || !$element->access->isAllowed()) {
        continue;
      }

      $link = $element->link;

      $items[] = [
        'title' => $link->getTitle(),
        'url' => $link->getUrlObject()->toString(),
        'children' => $this->normalizeTree($element->subtree),
      ];
    }

    return $items;
  }

}
