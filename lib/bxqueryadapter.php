<?php

namespace BX\Data\Provider;

use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;

class BxQueryAdapter
{
    /**
     * @var QueryCriteriaInterface
     */
    private $query;

    public function __construct(QueryCriteriaInterface $query)
    {
        $this->query = $query;
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return BxQueryAdapter
     */
    public static function init(QueryCriteriaInterface $query): BxQueryAdapter
    {
        return new static($query);
    }

    /**
     * @return array
     */
    private function buildFilter(): array
    {
        $filter = [];
        foreach ($this->query->getCriteriaList() as $compareRule) {
            $operation = $compareRule;
            $key = $compareRule->getKey();
            $value = $compareRule->getCompareValue();

            switch ($operation) {
                case CompareRuleInterface::EQUAL:
                    $filter["={$key}"] = $value;
                    break;
                case CompareRuleInterface::EQUAL:
                    $filter["={$key}"] = $value;
                    break;
                case CompareRuleInterface::NOT:
                    $filter["!{$key}"] = $value;
                    break;
                case CompareRuleInterface::IN:
                    $filter["={$key}"] = $value;
                    break;
                case CompareRuleInterface::NOT_IN:
                    $filter["!{$key}"] = $value;
                    break;
                case CompareRuleInterface::BETWEEN:
                    $firstValue = $value[0] ?? null;
                    $secondValue = $value[1] ?? null;
                    $filter[">={$key}"] = $firstValue;
                    $filter["<={$key}"] = $secondValue;
                    break;
                case CompareRuleInterface::NOT_BETWEEN:
                    $firstValue = $value[0] ?? null;
                    $secondValue = $value[1] ?? null;
                    $filter["<={$key}"] = $firstValue;
                    $filter[">={$key}"] = $secondValue;
                    break;
                case CompareRuleInterface::LESS:
                    $filter["<{$key}"] = $value;
                    break;
                case CompareRuleInterface::LESS_OR_EQUAL:
                    $filter["<={$key}"] = $value;
                    break;
                case CompareRuleInterface::MORE:
                    $filter[">{$key}"] = $value;
                    break;
                case CompareRuleInterface::MORE_OR_EQUAL:
                    $filter[">={$key}"] = $value;
                    break;
                case CompareRuleInterface::LIKE:
                    $filter["%{$key}"] = $value;
                    break;
                case CompareRuleInterface::NOT_LIKE:
                    $filter["!%{$key}"] = $value;
                    break;
            }
        }

        return $filter;
    }

    /**
     * @return array
     */
    private function buildOrder(): array
    {
        $order = [];

        foreach ($this->query->getOrderBy()->getOrderData() as $key => $config) {
            $order[$key] = $config['isAscending'] === true ? 'ASC' : 'DESC';
        }

        return $order;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $select = $this->query->getSelect();
        $filter = $this->buildFilter();
        $order = $this->buildOrder();
        $limit = (int)$this->query->getLimit();
        $offset = (int)$this->query->getOffset();
        $group = $this->query->getGroup();

        $result = [];
        if (!empty($select)) {
            $result['select'] = $select;
        }

        if (!empty($filter)) {
            $result['filter'] = $filter;
        }

        if (!empty($order)) {
            $result['order'] = $order;
        }

        if ($limit > 0) {
            $result['limit'] = $limit;
        }

        if ($offset > 0) {
            $result['offset'] = $offset;
        }

        if (!empty($group)) {
            $result['group'] = $group;
        }

        return $result;
    }
}