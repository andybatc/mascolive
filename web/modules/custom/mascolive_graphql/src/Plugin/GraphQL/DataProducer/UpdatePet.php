<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Updates an existing pet node.
 */
#[DataProducer(
  id: 'update_pet',
  name: new TranslatableMarkup('Update Pet'),
  description: new TranslatableMarkup('Updates a pet node, or NULL if not allowed or not found.'),
  produces: new ContextDefinition(
    data_type: 'any',
    label: new TranslatableMarkup('Pet'),
  ),
  consumes: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Pet ID'),
    ),
    'data' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Pet data'),
    ),
  ],
)]
class UpdatePet extends NodeMutationBase {

  /**
   * Updates a pet.
   *
   * @param int $id
   *   The pet node ID.
   * @param array $data
   *   The submitted values to update.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The updated pet, or NULL on missing permission or wrong node.
   */
  public function resolve(int $id, array $data): ?NodeInterface {
    $node = Node::load($id);
    if (!$node || $node->bundle() !== 'pet') {
      return NULL;
    }
    if (!$this->hasEntityAccess($node, 'edit own pet content', 'edit any pet content')) {
      return NULL;
    }

    $this->applyPetData($node, $data);
    $node->save();

    return $node;
  }

}