<?php

namespace Drupal\graphql_search_api\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_search_api\Wrapper\SearchConnection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DataProducer(
 *   id = "graphql_search_api_index",
 *   name = @Translation("Search API search"),
 *   description = @Translation("Loads the search index and sets all arguments."),
 *   produces = @ContextDefinition("any",
 *     search_index = @Translation("Search index")
 *   ),
 *   consumes = {
 *     "index_id" = @ContextDefinition("string",
 *       label = @Translation("Index id")
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language"),
 *       required = FALSE
 *     ),
 *     "fulltext" = @ContextDefinition("any",
 *       label = @Translation("Fulltext search parameter"),
 *       required = FALSE
 *     ),
 *     "conditions" = @ContextDefinition("any",
 *       label = @Translation("Conditions parameter"),
 *       required = FALSE
 *     ),
 *     "condition_groups" = @ContextDefinition("any",
 *       label = @Translation("Condition groups parameter"),
 *       required = FALSE
 *     ),
 *     "sort" = @ContextDefinition("any",
 *       label = @Translation("Sort parameter"),
 *       required = FALSE
 *     ),
 *    "range" = @ContextDefinition("any",
 *       label = @Translation("Range parameter"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class SearchApiIndex extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\search_api\Query\Query
   */
  private $query;

  /**
   * RouteLoad constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * @param string index_id
   * @param string $language
   * @param array $fulltext
   * @param array $conditions
   * @param array $condition_groups
   * @param array $range
   * @param array $sort
   *
   * @return mixed
   */
  public function resolve($index_id, $language, $fulltext, $conditions, $condition_groups, $sort, $range) {
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);

    $this->query = $index->query();

    if (!empty($fulltext)) {
      $this->setFulltextFields($fulltext);
    }

    if (!empty($conditions)) {
      $this->addConditions($conditions);
    }

    if (!empty($condition_groups)) {
      $this->addConditions($condition_groups);
    }

    // Adding sort parameters to the query.
    if (!empty($sort)) {
      foreach ($sort as $sort_item) {
        $this->query->sort($sort_item['field'], $sort_item['value']);
      }
    }

    if (!empty($language)) {
      $this->query->setLanguages([$language]);
    }

    if (!empty($range)) {
      $this->query->range($range['start'], $range['end']);
    }

    return SearchConnection::getInstance($this->query);
  }

  /**
   * Sets fulltext fields in the Search API query.
   *
   * @full_text_params
   *  Parameters containing fulltext keywords to be used as well as optional
   *  fields.
   */
  private function setFulltextFields($full_text_params) {

    // Check if keys is an array and if so set a conjunction.
    if (is_array($full_text_params['keys'])) {
      // If no conjunction was specified use OR as default.
      if (!empty($full_text_params['conjunction'])) {
        $full_text_params['keys']['#conjunction'] = $full_text_params['conjunction'];
      }
      else {
        $full_text_params['keys']['#conjunction'] = 'OR';
      }
    }

    // Set the keys in the query.
    $this->query->keys($full_text_params['keys']);

    // Set the optional fulltext fields if specified.
    if (!empty($full_text_params['fields'])) {
      $this->query->setFulltextFields($full_text_params['fields']);
    }
  }

  /**
   * Adds conditions to the Search API query.
   *
   * @conditions
   *  The conditions to be added.
   */
  private function addConditions($conditions) {

    // Loop through conditions to add them into the query.
    foreach ($conditions as $condition) {
      if (empty($condition['operator'])) {
        $condition['operator'] = '=';
      }
      if ($condition['value'] == 'NULL') {
        $condition['value'] = NULL;
      }
      // Set the condition in the query.
      $this->query->addCondition($condition['name'], $condition['value'], $condition['operator']);
    }
  }

  /**
   * Adds a condition group to the Search API query.
   *
   * @condition_group
   *  The conditions to be added.
   */
  private function addConditionGroup($condition_group_arg) {

    // Loop through the groups in the args.
    foreach ($condition_group_arg['groups'] as $group) {

      // Set default conjunction and tags.
      $group_conjunction = 'AND';
      $group_tags = [];

      // Set conjunction from args.
      if (isset($group['conjunction'])) {

        $group_conjunction = $group['conjunction'];
      }
      if (isset($group['tags'])) {
        $group_tags = $group['tags'];
      }

      // Create a single condition group.
      $condition_group = $this->query->createConditionGroup($group_conjunction, $group_tags);

      // Loop through all conditions and add them to the Group.
      foreach ($group['conditions'] as $condition) {

        $condition_group->addCondition($condition['name'], $condition['value'], $condition['operator']);
      }

      // Merge the single groups to the condition group.
      $this->query->addConditionGroup($condition_group);
    }
  }

}
