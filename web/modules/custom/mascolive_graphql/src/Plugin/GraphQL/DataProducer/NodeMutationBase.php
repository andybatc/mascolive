<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for pet/clinic mutations with shared access and field logic.
 */
abstract class NodeMutationBase extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * NodeMutationBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Access check: any-entity permission, or ownership plus own permission.
   */
  protected function hasEntityAccess(NodeInterface $node, string $own, string $any): bool {
    return $this->currentUser->hasPermission($any)
      || ($this->currentUser->id() === $node->getOwnerId() && $this->currentUser->hasPermission($own));
  }

  /**
   * Loads a pet_type taxonomy term by name, NULL if not found.
   */
  protected function loadPetTypeTerm(string $name): ?TermInterface {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'name' => $name,
      'vid' => 'pet_type',
    ]);
    return $terms ? reset($terms) : NULL;
  }

  /**
   * Loads a node only if it is a clinic, NULL otherwise.
   */
  protected function loadClinic(int $id): ?NodeInterface {
    $clinic = Node::load($id);
    return $clinic && $clinic->bundle() === 'clinic' ? $clinic : NULL;
  }

  /**
   * Applies non-null pet input data to the node.
   */
  protected function applyPetData(NodeInterface $node, array $data): void {
    if (!empty($data['title'])) {
      $node->setTitle($data['title']);
    }
    if (!empty($data['petType'])) {
      $term = $this->loadPetTypeTerm($data['petType']);
      if ($term) {
        $node->set('field_pet_type', $term);
      }
    }
    if (!empty($data['clinicId'])) {
      $clinic = $this->loadClinic((int) $data['clinicId']);
      if ($clinic) {
        $node->set('field_clinic', $clinic);
      }
    }
  }

  /**
   * Applies non-null clinic input data to the node.
   */
  protected function applyClinicData(NodeInterface $node, array $data): void {
    if (!empty($data['title'])) {
      $node->setTitle($data['title']);
    }
    // Input keys (address, specialty) map to real fields field_address/field_specialty.
    foreach (['address' => 'field_address', 'specialty' => 'field_specialty'] as $key => $field) {
      if (!empty($data[$key])) {
        $node->set($field, $data[$key]);
      }
    }
  }

}