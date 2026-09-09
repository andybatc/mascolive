<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphql\Attribute\DataProducer;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Creates a new clinic node.
 */
#[DataProducer(
  id: 'create_clinic',
  name: new TranslatableMarkup('Create Clinic'),
  description: new TranslatableMarkup('Creates a new clinic node, or NULL if not allowed or missing title.'),
  produces: new ContextDefinition(
    data_type: 'any',
    label: new TranslatableMarkup('Clinic'),
  ),
  consumes: [
    'data' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Clinic data'),
    ),
  ],
)]
class CreateClinic extends NodeMutationBase {

  /**
   * Creates a clinic.
   *
   * @param array $data
   *   The submitted values for the clinic.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created clinic, or NULL on missing permission or title.
   */
  public function resolve(array $data): ?NodeInterface {
    if (!$this->currentUser->hasPermission('create clinic content') || empty($data['title'])) {
      return NULL;
    }

    $node = Node::create([
      'type' => 'clinic',
      'title' => $data['title'],
    ]);
    $this->applyClinicData($node, $data);
    $node->save();

    return $node;
  }

}