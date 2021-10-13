<?php

namespace BX\Data\Provider;

use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;

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

    private static function getOperation(string $name, $value): array
    {
        switch (true) {
            case strpos($name, '<=') === 0:
                $name = str_replace('<=', '', $name);
                return [$name, CompareRuleInterface::LESS_OR_EQUAL];
            case strpos($name, '>=') === 0:
                $name = str_replace('>=', '', $name);
                return [$name, CompareRuleInterface::MORE_OR_EQUAL];
            case strpos($name, '!%') === 0:
                $name = str_replace('!%', '', $name);
                return [$name, CompareRuleInterface::NOT_LIKE];
            case strpos($name, '><') === 0:
                $name = str_replace('><', '', $name);
                return [$name, CompareRuleInterface::BETWEEN];
            case strpos($name, '%') === 0:
                $name = str_replace('%', '', $name);
                return [$name, CompareRuleInterface::LIKE];
            case strpos($name, '!') === 0:
                $name = str_replace('!', '', $name);
                return [$name, !is_array($value) ? CompareRuleInterface::NOT : CompareRuleInterface::NOT_IN];
            case strpos($name, '<') === 0:
                $name = str_replace('<', '', $name);
                return [$name, CompareRuleInterface::LESS];
            case strpos($name, '>') === 0:
                $name = str_replace('>', '', $name);
                return [$name, CompareRuleInterface::MORE];
            default:
                $name = str_replace('=', '', $name);
                return [$name, !is_array($value) ? CompareRuleInterface::EQUAL : CompareRuleInterface::IN];
        }
    }

    /**
     * @param CompareRuleInterface $compareRule
     * @param string $name
     * @param $value
     * @param bool $isOrLogic
     * @return CompareRuleInterface
     */
    private static function addCompareRule(
        CompareRuleInterface $compareRule,
        string $name,
        $value,
        bool $isOrLogic = false
    ): CompareRuleInterface
    {
        [$name, $operation] = static::getOperation($name, $value);
        return $isOrLogic ? $compareRule->or($name, $operation, $value) : $compareRule->and($name, $operation, $value);
    }


    private static function addCriteria(
        QueryCriteriaInterface $query,
        array $filterData,
        CompareRuleInterface $actualCompareRule = null
    ): ?CompareRuleInterface
    {
        $logic = strtoupper($filterData['LOGIC'] ?? '');
        unset($filterData['LOGIC']);

        $isOrLogic = $logic === 'OR';
        $firstCratedCompareRule = null;
        foreach ($filterData as $name => $value) {
            if (!is_int($name) && is_array($value) && !empty($value)) {
                $actualCompareRule = static::addCriteria($query, $value, $actualCompareRule);
                if (empty($firstCratedCompareRule)) {
                    $firstCratedCompareRule = $actualCompareRule;
                }
            } elseif (is_string($name) && !empty($name)) {
                if (empty($actualCompareRule)) {
                    [$name, $operation] = static::getOperation($name, $value);
                    $actualCompareRule = $query->addCriteria($name, $operation, $value);
                    if (empty($firstCratedCompareRule)) {
                        $firstCratedCompareRule = $actualCompareRule;
                    }
                } else {
                    $actualCompareRule = static::addCompareRule($actualCompareRule, $name, $value, $isOrLogic);
                }
            }
        }

        return $firstCratedCompareRule;
    }

    public function initFromArray(array $params): BxQueryAdapter
    {
        $filterData = $params['filter'] ?? null;
        $selectData = $params['select'] ?? null;
        $limit = (int)($params['limit'] ?? 0);
        $offset = (int)($params['offset'] ?? 0);
        $groupData = $params['group'] ?? null;
        $orderData = $params['order'] ?? null;

        $query = new QueryCriteria();
        if (!empty($selectData) && is_array($selectData)) {
            $query->setSelect($selectData);
        }

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        if ($offset > 0) {
            $query->setOffset($offset);
        }

        if (!empty($groupData) && is_array($groupData)) {
            $query->setGroup($groupData);
        }

        if (!empty($filterData) && is_array($filterData)) {
            static::addCriteria($query, $filterData);
        }

        if (!empty($orderData) && is_array($orderData)) {
            foreach ($orderData as $name => $direction) {
                if (is_string($name) && !empty($name)) {
                    $query->setOrderBy($name, strtoupper($direction) !== 'DESC');
                }
            }
        }
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return BxQueryAdapter
     */
    public static function init(QueryCriteriaInterface $query): BxQueryAdapter
    {
        return new static($query);
    }

    public function getQuery(): QueryCriteriaInterface
    {
        return $this->query;
    }

    private function buildFilterRule(CompareRuleInterface $compareRule): array
    {
        $filter = [];
        $operation = $compareRule->getOperation();
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

        foreach ($compareRule->getAndList() as $andCompareRule) {
            $filter = array_merge($filter, $this->buildFilterRule($andCompareRule));
        }

        foreach ($compareRule->getOrList() as $orCompareRule) {
            $filter = [
                'LOGIC' => 'OR',
                $filter,
                $this->buildFilterRule($orCompareRule)
            ];
        }

        return $filter;
    }

    /**
     * @return array
     */
    private function buildFilter(): array
    {
        $filter = [];
        foreach ($this->query->getCriteriaList() as $compareRule) {
            if (!empty($compareRule->getOrList())) {
                $filter[] = $this->buildFilterRule($compareRule);
            } else {
                $filter = array_merge($filter, $this->buildFilterRule($compareRule));
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
     * @param string|null $pkName
     * @return bool
     */
    public function isEqualPkQuery(string $pkName = null): bool
    {
        if (empty($pkName)) {
            return false;
        }

        $criteriaList = $this->query->getCriteriaList();
        $countCriteria = count($criteriaList);
        if ($countCriteria > 1 || $countCriteria === 0) {
            return false;
        }

        $compareRule = current($criteriaList);
        if ($compareRule->getOperation() !== CompareRuleInterface::EQUAL) {
            return false;
        }

        if ($compareRule->getKey() !== $pkName) {
            return false;
        }

        if (empty($compareRule->getCompareValue())) {
            return false;
        }

        return true;
    }

    /**
     * @param string|null $pkName
     * @param string $operation
     * @return array|mixed|null
     */
    public function getPkValueFromQuery(string $pkName = null, string $operation = CompareRuleInterface::EQUAL)
    {
        if (empty($pkName)) {
            return null;
        }

        $criteriaList = $this->query->getCriteriaList();
        if (empty($criteriaList)) {
            return null;
        }

        $result = [];
        foreach ($criteriaList as $compareRule) {
            if ($compareRule->getOperation() === $operation && $compareRule->getKey() === $pkName) {
                $result[] = $compareRule->getCompareValue();
            }
        }

        if (empty($result)) {
            return null;
        }

        if (count($result) === 1) {
            return current($result);
        }

        return $result;
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