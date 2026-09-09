<?php

declare(strict_types=1);

namespace Drupal\mascolive_graphql\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\Attribute\SchemaExtension;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Exposes pet/clinic demo content through the composable schema.
 */
#[SchemaExtension(
  id: "mascolive",
  name: "MascoLive extension",
  description: "Exposes pet and clinic entities for the MascoLive demo.",
  schema: "composable"
)]
class MascoliveSchemaExtension extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // By id.
    $registry->addFieldResolver('Query', 'pet',
      $builder->produce('entity_load')
        ->map('type', $builder->fromValue('node'))
        ->map('bundles', $builder->fromValue(['pet']))
        ->map('id', $builder->fromArgument('id'))
    );
    $registry->addFieldResolver('Query', 'clinic',
      $builder->produce('entity_load')
        ->map('type', $builder->fromValue('node'))
        ->map('bundles', $builder->fromValue(['clinic']))
        ->map('id', $builder->fromArgument('id'))
    );

    // Lists (entity_query -> entity_load_multiple).
    $registry->addFieldResolver('Query', 'pets',
      $builder->compose(
        $builder->produce('entity_query', [
          'type' => $builder->fromValue('node'),
          'bundles' => $builder->fromValue(['pet']),
          'limit' => $builder->fromValue(50),
        ]),
        $builder->produce('entity_load_multiple', [
          'type' => $builder->fromValue('node'),
          'ids' => $builder->fromParent(),
        ])
      )
    );
    $registry->addFieldResolver('Query', 'clinics',
      $builder->compose(
        $builder->produce('entity_query', [
          'type' => $builder->fromValue('node'),
          'bundles' => $builder->fromValue(['clinic']),
          'limit' => $builder->fromValue(50),
        ]),
        $builder->produce('entity_load_multiple', [
          'type' => $builder->fromValue('node'),
          'ids' => $builder->fromParent(),
        ])
      )
    );

    // Resolve node abstract type to concrete types.
    $registry->addTypeResolver('entity:node', function ($node) {
      return $node->bundle() === 'pet' ? 'Pet' : 'Clinic';
    });

    // Pet fields.
    $registry->addFieldResolver('Pet', 'id',
      $builder->produce('entity_id')->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Pet', 'title',
      $builder->produce('entity_label')->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Pet', 'clinic',
      $builder->compose(
        $builder->produce('entity_reference')
          ->map('entity', $builder->fromParent())
          ->map('field', $builder->fromValue('field_clinic')),
        $builder->callback(fn($items) => is_array($items) && count($items) ? $items[0] : NULL)
      )
    );
    $registry->addFieldResolver('Pet', 'petType',
      $builder->compose(
        $builder->produce('entity_reference')
          ->map('entity', $builder->fromParent())
          ->map('field', $builder->fromValue('field_pet_type')),
        $builder->callback(fn($items) => is_array($items) && count($items) ? $items[0] : NULL),
        $builder->produce('entity_label')->map('entity', $builder->fromParent())
      )
    );

    // Clinic fields.
    $registry->addFieldResolver('Clinic', 'id',
      $builder->produce('entity_id')->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Clinic', 'title',
      $builder->produce('entity_label')->map('entity', $builder->fromParent())
    );
    $fieldString = fn(string $field) => fn($node) => $node->hasField($field) && !$node->get($field)->isEmpty() ? $node->get($field)->value : NULL;
    $registry->addFieldResolver('Clinic', 'address', $builder->callback($fieldString('field_address')));
    $registry->addFieldResolver('Clinic', 'specialty', $builder->callback($fieldString('field_specialty')));
    $registry->addFieldResolver('Clinic', 'pets',
      $builder->compose(
        $builder->produce('entity_id')->map('entity', $builder->fromParent()),
        $builder->callback(fn($parentId) => [['field' => 'field_clinic', 'value' => $parentId]]),
        $builder->produce('entity_query', [
          'type' => $builder->fromValue('node'),
          'conditions' => $builder->fromParent(),
          'allowed_filters' => $builder->fromValue(['field_clinic']),
          'bundles' => $builder->fromValue(['pet']),
        ]),
        $builder->produce('entity_load_multiple', [
          'type' => $builder->fromValue('node'),
          'ids' => $builder->fromParent(),
        ])
      )
    );

    // Mutations.
    $registry->addFieldResolver('Mutation', 'createPet',
      $builder->produce('create_pet')
        ->map('data', $builder->fromArgument('data'))
    );
    $registry->addFieldResolver('Mutation', 'updatePet',
      $builder->produce('update_pet')
        ->map('id', $builder->fromArgument('id'))
        ->map('data', $builder->fromArgument('data'))
    );
    $registry->addFieldResolver('Mutation', 'deletePet',
      $builder->produce('delete_pet')
        ->map('id', $builder->fromArgument('id'))
    );
    $registry->addFieldResolver('Mutation', 'createClinic',
      $builder->produce('create_clinic')
        ->map('data', $builder->fromArgument('data'))
    );
    $registry->addFieldResolver('Mutation', 'updateClinic',
      $builder->produce('update_clinic')
        ->map('id', $builder->fromArgument('id'))
        ->map('data', $builder->fromArgument('data'))
    );
    $registry->addFieldResolver('Mutation', 'deleteClinic',
      $builder->produce('delete_clinic')
        ->map('id', $builder->fromArgument('id'))
    );
  }

}