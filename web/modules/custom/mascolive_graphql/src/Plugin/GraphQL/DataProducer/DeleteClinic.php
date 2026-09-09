<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;

/**
 * Deletes a clinic node.
 */
#[DataProducer(
  id: 'delete_clinic',
  name: new TranslatableMarkup('Delete Clinic'),
  description: new TranslatableMarkup('Deletes a clinic node, returns TRUE on success.'),
  produces: new ContextDefinition(
    data_type: 'boolean',
    label: new TranslatableMarkup('Deleted'),
  ),
  consumes: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Clinic ID'),
    ),
  ],
)]
class DeleteClinic extends NodeMutationBase {

  /**
   * Deletes a clinic.
   *
   * @param int $id
   *   The clinic node ID.
   *
   * @return bool
   *   TRUE if deleted, FALSE on missing permission or wrong node.
   */
  public function resolve(int $id): bool {
    $node = Node::load($id);
    if (!$node || $node->bundle() !== 'clinic') {
      return FALSE;
    }
    if (!$this->hasEntityAccess($node, 'delete own clinic content', 'delete any clinic content')) {
      return FALSE;
    }

    $node->delete();

    return TRUE;
  }

}