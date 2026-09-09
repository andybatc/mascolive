<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Creates a new pet node.
 */
#[DataProducer(
  id: 'create_pet',
  name: new TranslatableMarkup('Create Pet'),
  description: new TranslatableMarkup('Creates a new pet node, or NULL if not allowed or missing title.'),
  produces: new ContextDefinition(
    data_type: 'any',
    label: new TranslatableMarkup('Pet'),
  ),
  consumes: [
    'data' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Pet data'),
    ),
  ],
)]
class CreatePet extends NodeMutationBase {

  /**
   * Creates a pet.
   *
   * @param array $data
   *   The submitted values for the pet.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created pet, or NULL on missing permission or title.
   */
  public function resolve(array $data): ?NodeInterface {
    if (!$this->currentUser->hasPermission('create pet content') || empty($data['title'])) {
      return NULL;
    }

    $node = Node::create([
      'type' => 'pet',
      'title' => $data['title'],
    ]);
    $this->applyPetData($node, $data);
    $node->save();

    return $node;
  }

}