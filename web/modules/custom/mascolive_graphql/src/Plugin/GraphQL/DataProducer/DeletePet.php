<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;

/**
 * Deletes a pet node.
 */
#[DataProducer(
  id: 'delete_pet',
  name: new TranslatableMarkup('Delete Pet'),
  description: new TranslatableMarkup('Deletes a pet node, returns TRUE on success.'),
  produces: new ContextDefinition(
    data_type: 'boolean',
    label: new TranslatableMarkup('Deleted'),
  ),
  consumes: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Pet ID'),
    ),
  ],
)]
class DeletePet extends NodeMutationBase {

  /**
   * Deletes a pet.
   *
   * @param int $id
   *   The pet node ID.
   *
   * @return bool
   *   TRUE if deleted, FALSE on missing permission or wrong node.
   */
  public function resolve(int $id): bool {
    $node = Node::load($id);
    if (!$node || $node->bundle() !== 'pet') {
      return FALSE;
    }
    if (!$this->hasEntityAccess($node, 'delete own pet content', 'delete any pet content')) {
      return FALSE;
    }

    $node->delete();

    return TRUE;
  }

}