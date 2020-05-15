<?php

namespace Drupal\graphql_search_api\Plugin\GraphQL\Schema;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;
use Drupal\graphql_search_api\Wrapper\SearchConnection;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;

/**
 *
 *
 * @Schema(
 *   id = "search_api_schema",
 *   name = "GraphQL Search API schema"
 * )
 */
class SearchApiSchema extends SdlSchemaPluginBase {




  /**
   * {@inheritdoc}
   */
  public function getResolverRegistry() {
    $builder = new ResolverBuilder();
    $registry = new ResolverRegistry();

    $this->addSearchFields($registry, $builder);

    // NodeInterface is deprecated and should not be used any more.
    $registry->addTypeResolver('EntityInterface', function ($value) {
      if ($value instanceof NodeInterface) {
        return $this->camelize($value->bundle());
      }
    });

    return $registry;
  }

  /**
   * Adds the search fields to the query.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addSearchFields(ResolverRegistry $registry, ResolverBuilder $builder): void {
    $registry->addFieldResolver('Query', 'search_api_search', $builder->produce('graphql_search_api_index')
      ->map('index_id', $builder->fromArgument('index_id'))
      ->map('fulltext', $builder->fromArgument('fulltext'))
      ->map('range', $builder->fromArgument('range'))
      ->map('sort', $builder->fromArgument('sort'))
      ->map('conditions', $builder->fromArgument('conditions'))
      ->map('condition_groups', $builder->fromArgument('condition_groups'))
      ->map('language', $builder->fromArgument('language'))
    );

    $registry->addFieldResolver('SearchResultResponse', 'result_count',
      $builder->callback(function (SearchConnection $connection) {
        return $connection->resultCount();
      })
    );

    $registry->addFieldResolver('SearchResultResponse', 'documents',
      $builder->callback(function (SearchConnection $connection) {
        return $connection->getDocuments();
      })
    );

    $registry->addFieldResolver('SearchResultItemDoc', 'score',
      $builder->callback(function (ItemInterface $item) {
        return $item->getScore();
      })
    );

    $registry->addFieldResolver('SearchResultItemDoc', 'id',
      $builder->callback(function (ItemInterface $item) {
        return $item->getId();
      })
    );

    $registry->addFieldResolver('SearchResultItemDoc', 'entity', $builder->compose(
      $builder->callback(function (ItemInterface $item) {
        // Split of the item id string to get the right entity information.
        // The id looks like entity:node/31:de.
        [$datasource, $raw_id] = Utility::splitCombinedId($item->getId());

        [, $type] = explode(':', $datasource);
        [$id, $language] = explode(':', $raw_id);

        return [
          'type' => $type,
          'id' => $id,
          'language' => $language,
        ];

      }),
      $builder->produce('entity_load')
        ->map('type', $builder->callback(function ($parent) {
          return $parent['type'];
        }))
        ->map('id', $builder->callback(function ($parent) {
          return $parent['id'];
        }))
        ->map('language', $builder->callback(function ($parent) {
          return $parent['language'];
        })))
    );

    $registry->addFieldResolver('entity', 'id',
      $builder->produce('entity_id')
        ->map('entity', $builder->fromParent())
    );
  }

  private function camelize($input) {
    return str_replace('_', '', ucwords($input, '_'));
  }

}
