<?php

declare(strict_types=1);

namespace Drupal\hello_api\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Provides ship detail data as a REST resource.
 */
#[RestResource(
  id: 'ship_detail',
  label: new TranslatableMarkup('Ship Detail'),
  uri_paths: [
    'canonical' => '/api/ships/{id}',
  ],
)]
final class ShipDetailResource extends ResourceBase {

  /**
   * The entity type manager.
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get($id): ResourceResponse {
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($id);

    if (!$node || $node->bundle() !== 'ship') {
      throw new NotFoundHttpException();
    }

    $resource = [
      'id' => (int) $node->id(),
      'title' => $node->label(),
    ];

    $response = new ResourceResponse($resource);
    $response->addCacheableDependency($node);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method): Route {
    $route = parent::getBaseRoute($canonical_path, $method);
    // Set ID validation pattern.
    if ($method !== 'POST') {
      $route->setRequirement('id', '\d+');
    }
    return $route;
  }

}
