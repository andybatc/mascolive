<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Updates an existing clinic node.
 */
#[DataProducer(
  id: 'update_clinic',
  name: new TranslatableMarkup('Update Clinic'),
  description: new TranslatableMarkup('Updates a clinic node, or NULL if not allowed or not found.'),
  produces: new ContextDefinition(
    data_type: 'any',
    label: new TranslatableMarkup('Clinic'),
  ),
  consumes: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Clinic ID'),
    ),
    'data' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Clinic data'),
    ),
  ],
)]
class UpdateClinic extends NodeMutationBase {

  /**
   * Updates a clinic.
   *
   * @param int $id
   *   The clinic node ID.
   * @param array $data
   *   The submitted values to update.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The updated clinic, or NULL on missing permission or wrong node.
   */
  public function resolve(int $id, array $data): ?NodeInterface {
    $node = Node::load($id);
    if (!$node || $node->bundle() !== 'clinic') {
      return NULL;
    }
    if (!$this->hasEntityAccess($node, 'edit own clinic content', 'edit any clinic content')) {
      return NULL;
    }

    $this->applyClinicData($node, $data);
    $node->save();

    return $node;
  }

}