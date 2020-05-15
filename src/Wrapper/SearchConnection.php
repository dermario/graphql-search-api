<?php

namespace Drupal\graphql_search_api\Wrapper;

use Drupal\search_api\Query\QueryInterface;

/**
 * Class SearchConnection.
 *
 * This is a singleton to avoid duplicate queries for each field.
 */
class SearchConnection {

  /**
   * Keeps the instance for the singleton.
   *
   * @var \Drupal\graphql_search_api\Wrapper\SearchConnection
   */
  protected static $instance = NULL;

  /**
   * Search api query object.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  private $query;

  /**
   * Resultset of a executed query.
   *
   * @var \Drupal\search_api\Query\ResultSetInterface
   */
  private $resultset;

  /**
   * Constructor.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search api query object.
   */
  private function __construct(QueryInterface $query) {
    $this->query = $query;
  }

  /**
   * Creates one instance of this class.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search api query object.
   *
   * @return \Drupal\graphql_search_api\Wrapper\SearchConnection
   *   Instance of itself.
   */
  public static function getInstance(QueryInterface $query) {
    if (NULL === self::$instance) {
      self::$instance = new self($query);
    }
    return self::$instance;
  }

  /**
   * Returns the total number of items in the resultset.
   *
   * @return int
   *   Number of items in the resultset.
   */
  public function resultCount() {
    return (int) $this->getResultset()->getResultCount();
  }

  /**
   * Returns all items in the resultset.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   Search api result items.
   */
  public function getDocuments() {
    return $this->getResultset()->getResultItems();
  }

  /**
   * Executes the search query and stores the resultset in a class property.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   Search api resultset.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  private function getResultset() {
    if (empty($this->resultset)) {
      $this->resultset = $this->query->execute();
    }
    return $this->resultset;
  }

}
