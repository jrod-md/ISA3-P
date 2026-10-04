<?php
declare(strict_types=1);

namespace Marketplace\Provider;

use PDO;

final class ProductRepository
{
    private const SORT_MAP = [
        'default' => 'id ASC',
        'price_asc' => 'price ASC, id ASC',
        'price_desc' => 'price DESC, id ASC',
        'name_asc' => 'name ASC, id ASC',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(array $criteria): array
    {
        [$sql, $params] = $this->buildQuery($criteria);
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return array{0:string,1:array<string,int|string>} */
    public function buildQuery(array $criteria): array
    {
        $sql = 'SELECT id, sku, name, description, category, brand, price, currency, stock '
             . 'FROM products WHERE active = 1';
        $params = [];

        if ($criteria['q'] !== null) {
            $sql .= ' AND (name LIKE :q_name OR description LIKE :q_description OR brand LIKE :q_brand)';
            $like = '%' . $criteria['q'] . '%';
            $params[':q_name'] = $like;
            $params[':q_description'] = $like;
            $params[':q_brand'] = $like;
        }
        if ($criteria['category'] !== null) {
            $sql .= ' AND category = :category';
            $params[':category'] = $criteria['category'];
        }
        if ($criteria['brand'] !== null) {
            $sql .= ' AND brand LIKE :brand';
            $params[':brand'] = '%' . $criteria['brand'] . '%';
        }
        if ($criteria['min_price'] !== null) {
            $sql .= ' AND price >= :min_price';
            $params[':min_price'] = (string) $criteria['min_price'];
        }
        if ($criteria['max_price'] !== null) {
            $sql .= ' AND price <= :max_price';
            $params[':max_price'] = (string) $criteria['max_price'];
        }
        if ($criteria['min_stock'] !== null) {
            $sql .= ' AND stock >= :min_stock';
            $params[':min_stock'] = (int) $criteria['min_stock'];
        }

        $orderBy = self::SORT_MAP[$criteria['sort']] ?? self::SORT_MAP['default'];
        $limit = max(1, min(100, (int) $criteria['limit']));
        $sql .= " ORDER BY {$orderBy} LIMIT {$limit}";
        return [$sql, $params];
    }
}

