<?php

namespace Bbc\Features;

/**
 * Class Recipe
 *
 * @package Bbc\Features
 */
class Recipe
{
    /**
     * @var \Bbc\Features\Model\Recipe[]
     */
    protected $items;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var \PDO
     */
    protected $db;

    /**
     * Query params
     *
     * @var array
     */
    protected $params;

    /**
     * List of filter groups
     *
     * @var array
     */
    protected $filters;

    protected $limit = 10;

    protected $offset = 0;

    protected $itemsReturned;

    /**
     * Recipe constructor.
     *
     * @param \PDO $db
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
        // initial query with joined ingredients' data
        $this->query = 'SELECT main_table.* FROM recipe as main_table 
          LEFT JOIN ingridient as ingridient_table ON ingridient_table.recipe_id = main_table.id';
    }

    /**
     * Loads all recipes
     *
     * @return Model\Recipe[]
     */
    public function getAllItems()
    {
        if (!empty($this->filters)) {
            $this->applyFilters();
        }
        $stmt = $this->prepareSqlStatement($this->query, $this->params);
        $items = $stmt->fetchAll();
        // build recipe models list
        foreach ($items as $item) {
            $this->items[$item['id']] = new Model\Recipe($this, $item);
        }
        $this->itemsReturned = count($this->items);
        return (array)$this->items;
    }

    /**
     * Adds filters to main query
     *
     * @param array $filter
     */
    public function addFilter(array $filter = [])
    {
        if (isset($filter['limit'])) {
            $this->limit = $filter['limit'];
            unset($filter['limit']);
        }
        if (isset($filter['offset'])) {
            $this->offset = $filter['offset'];
            unset($filter['offset']);
        }
        foreach ($filter as $table => $params) {
            $queryConcat = [];
            foreach ($params['field'] as $index => $param) {
                $q = "$table.$param {$params['op'][$index]}";
                // if this is a in filter there's no need to escaping values
                if ($params['op'][$index] == 'in') {
                    $queryConcat[] = $q . " {$params['value'][$index]}";
                } else {
                    $queryConcat[] = $q . " '{$params['value'][$index]}'";
                }
            }
            $this->filters[$table] = $queryConcat;
        }
    }

    /**
     * Loads recipe by id
     *
     * @param $id
     * @return Model\Recipe
     */
    public function load($id)
    {
        if (!isset($this->items[$id])) {
            $q = 'SELECT main_table.* FROM recipe as main_table WHERE id=:id';
            $stmt = $stmt = $this->prepareSqlStatement($q, [':id' => $id]);
            $item = $stmt->fetch();
            $this->items[$id] = new Model\Recipe($this, $item);
        }
        return $this->items[$id];
    }

    /**
     * Adds gallery data to recipe object
     *
     * @param Model\Recipe $recipe
     * @return $this
     */
    public function addGalleryData(Model\Recipe $recipe)
    {
        $recipeId = $recipe->getData('id');
        $q = 'SELECT gallery_table.image FROM gallery as gallery_table WHERE recipe_id = :recipe_id';
        $stmt = $this->prepareSqlStatement($q, [':recipe_id' => $recipeId], false);
        $gallery = $stmt->fetchAll();
        $recipe->setData(['gallery' => $gallery]);
        return $this;
    }

    /**
     * Adds ingredients data to recipe object
     *
     * @param Model\Recipe $recipe
     * @return $this
     */
    public function addIngredientsData(Model\Recipe $recipe)
    {
        $recipeId = $recipe->getData('id');
        $q = 'SELECT ingridient_table.name, ingridient_table.qty, ingridient_table.qty_type  FROM ingridient as ingridient_table WHERE recipe_id = :recipe_id';
        $stmt = $this->prepareSqlStatement($q, [':recipe_id' => $recipeId], false);
        $gallery = $stmt->fetchAll();
        $recipe->setData(['ingredients' => $gallery]);
        return $this;
    }

    /**
     * Gets total number of records
     *
     * @return string
     */
    public function getTotal()
    {
        $query = 'SELECT count(*) FROM recipe as main_table';
        return $this->db->query($query)->fetchColumn();
    }

    /**
     * Gets current offset for the query
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Gets current limit for the query
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Prepares query for fetching data
     *
     * @param $query
     * @param array $params
     * @param bool $main
     * @return \PDOStatement
     */
    protected function prepareSqlStatement($query, $params = [], $main = true)
    {
        // if this is a main query for the recipe listing
        if ($main) {
            $query .= ' GROUP BY id';
            if ($this->limit) {
                $query .= " LIMIT {$this->limit}";
                if ($this->offset) {
                    $query .= " OFFSET {$this->offset}";
                }
            }
        }
        $stmt = $this->db->prepare($query);
        foreach ($params as $key=>$param) {
            $stmt->bindParam($key, $param);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * Prepares query with applied filters
     *
     * @return $this
     */
    protected function applyFilters()
    {
        $this->query .= " WHERE ";
        $queryParts = [];
        foreach ($this->filters as $filter) {
            if (empty($filter)) continue;
            $queryParts[] = '(' . implode(' OR ', $filter) . ')';
        }
        $this->query .= implode(' AND ', $queryParts);
        return $this;
    }
}