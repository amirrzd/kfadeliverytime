<?php

declare(strict_types=1);

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, April 2021
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;

class KfaDeliveryTimeOrderListing
{
    private string $deliverytime_column = 'deliverytime';
    
    public function hookActionOrderGridDefinitionModifier(
        array $params,
        bool $add_deliverytime,
        bool $add_carrier_name
    ): void {
        if ($add_deliverytime) {
            $this->addDeliveryTimeColumn($params);
        }
        if ($add_carrier_name) {
            $this->addCarrierNameColumn($params);
        }
    }
    
    private function addDeliveryTimeColumn(array $params): void {
        $options = array(
            'field' => $this->deliverytime_column,
            'sortable' => false,
        );
        
        $dataColumn = new DataColumn($this->deliverytime_column);
        $dataColumn->setName('زمان تحویل');
        $dataColumn->setOptions($options);
        $params['definition']->getColumns()->addAfter('osname', $dataColumn);
        
        $typeOptions = array(
            'required' => false,
            'attr' => array(
                'placeholder' => 'جستجوی زمان تحویل',
            ),
        );
        
        //$filter = new Filter($this->deliverytime_column, TextType::class);
        $filter = new Filter($this->deliverytime_column, DateRangeType::class);
        $filter->setAssociatedColumn($this->deliverytime_column);
        $filter->setTypeOptions($typeOptions);
        $params['definition']->getFilters()->add($filter);
    }
    
    private function addCarrierNameColumn(array $params): void {
        $options = array(
            'field' => 'carrier_name',
            'sortable' => true,
        );
        
        $dataColumn = new DataColumn('carrier_name');
        $dataColumn->setName('حامل');
        $dataColumn->setOptions($options);
        $params['definition']->getColumns()->addAfter('osname', $dataColumn);
        
        $typeOptions = array(
            'required' => false,
            'attr' => array(
                'placeholder' => 'جستجوی حامل',
            ),
        );
        
        $filter = new Filter('carrier_name', TextType::class);
        $filter->setAssociatedColumn('carrier_name');
        $filter->setTypeOptions($typeOptions);
        $params['definition']->getFilters()->add($filter);
    }
    
    public function hookActionOrderGridQueryBuilderModifier(
        array $params,
        bool $add_deliverytime,
        bool $add_carrier_name
    ): void {
        if ($add_deliverytime) {
            $this->selectDeliveryTime([
                'search_query_builder' => $params['search_query_builder'],
                'search_criteria' => $params['search_criteria'],
            ], true);

            $filters = $params['search_criteria']->getFilters();
            $deliverytime_filter = $filters[$this->deliverytime_column] ?? null;
            if (is_array($deliverytime_filter)
                && (!empty($deliverytime_filter['from']) || !empty($deliverytime_filter['to']))) {
                $this->selectDeliveryTime([
                    'search_query_builder' => $params['count_query_builder'],
                    'search_criteria' => $params['search_criteria'],
                ], false);
            }
        }

        if ($add_carrier_name) {
            $this->selectCarrierName($params);
        }
    }

    
    private function selectDeliveryTime(array $params, bool $include_column): void {
        $searchQueryBuilder = $params['search_query_builder'];
        $searchCriteria = $params['search_criteria'];
        
        $searchQueryBuilder->leftJoin(
            'o',
            _DB_PREFIX_ . 'kfadeliverytime_cart',
            'kdt',
            'kdt.`id_cart` = o.`id_cart`'
        );
        if ($include_column) {
            $searchQueryBuilder->addSelect("kdt.`value` AS `$this->deliverytime_column`");

            if ($searchCriteria->getOrderBy() === $this->deliverytime_column) {
                $searchQueryBuilder->orderBy('o.`id_order`', 'DESC');
            }
        }

        $filters = $searchCriteria->getFilters();
        $filter_value = $filters[$this->deliverytime_column] ?? null;
        if (!is_array($filter_value)) {
            return;
        }

        if (is_string($filter_value['from'] ?? null)
            && ($from = $this->normalizeFilterDate($filter_value['from'], false)) !== null) {
            $searchQueryBuilder->andWhere("kdt.`date_start` >= :{$this->deliverytime_column}_from");
            $searchQueryBuilder->setParameter("{$this->deliverytime_column}_from", $from);
        }

        if (is_string($filter_value['to'] ?? null)
            && ($to = $this->normalizeFilterDate($filter_value['to'], true)) !== null) {
            $searchQueryBuilder->andWhere("kdt.`date_start` <= :{$this->deliverytime_column}_to");
            $searchQueryBuilder->setParameter("{$this->deliverytime_column}_to", $to);
        }
    }

    private function normalizeFilterDate(string $date, bool $end_of_day): ?string
    {
        if (KfaPersianDate::isSolarDate($date)) {
            $date = KfaPersianDate::stoG($date)->format('Y-m-d');
        } else {
            $parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed_date === false || $parsed_date->format('Y-m-d') !== $date) {
                return null;
            }

            $date = $parsed_date->format('Y-m-d');
        }

        return $date . ($end_of_day ? ' 23:59:59' : ' 00:00:00');
    }
    
    private function selectCarrierName(array $params): void {
        $searchQueryBuilder = $params['search_query_builder'];
        $searchCriteria = $params['search_criteria'];
        $searchQueryBuilder->leftJoin('o', _DB_PREFIX_ . 'carrier', 'carrier', 'carrier.`id_carrier` = o.`id_carrier`');
        $searchQueryBuilder->addSelect('carrier.`name` AS carrier_name');
        
        if ($searchCriteria->getOrderBy() === 'carrier_name') {
            $searchQueryBuilder->orderBy('`carrier_name`', $searchCriteria->getOrderWay());
        }
        
        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($filterName === 'carrier_name' && is_scalar($filterValue)) {
                $filterValue = (string) $filterValue;
                $searchQueryBuilder->andWhere('carrier.`name` LIKE :carrier_name');
                $searchQueryBuilder->setParameter('carrier_name', "%$filterValue%");
                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('`carrier_name` IS NULL');
                }
            }
        }
    }
}
