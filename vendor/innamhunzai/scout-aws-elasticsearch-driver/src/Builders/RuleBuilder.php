<?php

namespace InnamHunzai\ScoutAwsElastic\Builders;

use Exception;

abstract class RuleBuilder
{
    /**
     * @var array
     */
    protected $filters = [];
    
    /**
     * @var array
     */
    protected $multiMatches = [];
    
    /**
     * @var array
     */
    protected $musts = [];
    
    /**
     * @var array;
     */
    protected $mustNots = [];
    
    /**
     * @var array
     */
    protected $shoulds = [];
    
    /**
     * @var array
     */
    protected $ranges = [];
    
    /**
     * @var array
     */
    protected $rule = [];
    
    /**
     *
     *
     * @param array $rules
     * @return array
     */
    public function build(array $rules): array
    {
        // Process $rules
        collect($rules)->filter(function ($filter) {
            return !is_null($filter);
        })->each(function ($value, $filter) {
            if (method_exists($this, $filter)) {
                $this->$filter($value);
            }
        });
        
        if (!empty($this->filters)) {
            $this->rule['filter'] = $this->generate($this->filters, 'term');
        }
        
        if (!empty($this->musts)) {
            $this->rule['must'] = $this->generate($this->musts, 'match');
        }
        
        if (!empty($this->multiMatches)) {
            $this->rule['must'] = $this->generate($this->multiMatches, 'multi_match');
        }
        
        if (!empty($this->mustNots)) {
            $this->rule['must_not'] = $this->generate($this->mustNots, 'match');
        }
        
        if (!empty($this->shoulds)) {
            $this->rule['should'] = $this->generate($this->shoulds, 'match');
        }
        
        if (!empty($this->ranges)) {
            foreach ($this->ranges as $field => $values) {
                $array[$field] = $values;
                
                $this->rule[$values['type']][] = $this->generate($array, 'range');
            }
        }
        
        return $this->rule;
    }
    
    /**
     * Create an array formatted for elasticsearch.
     *
     * @param array $rules
     * @param string $type
     * @return array
     */
    protected function generate(array $rules, string $type): array
    {
        $array = [];
        
        collect($rules)->each(function ($rule, $field) use (&$array, $type) {
            if ($type === 'range') {
                $values = [];
                
                for ($i = 0; $i < count($rule['values']); $i += 2) {
                    $values[$rule['values'][$i]] = $rule['values'][$i + 1];
                }
                
                $array[$type] = [
                    $field => $values,
                ];
            } else if ($type === 'multi_match') {
                $a = [
                    'query'  => $rule['value'],
                    'fields' => $rule['fields'],
                ];
                
                if (array_key_exists('attributes', $rule) && !empty($rule['attributes'])) {
                    $a = array_merge($a, $rule['attributes']);
                }
                
                $array[] = [
                    $type => $a,
                ];
            } else if (array_key_exists('attributes', $rule) && !empty($rule['attributes'])) {
                if ($type === 'match') {
                    $rule['attributes']['query'] = $rule['value'];
                    $array[] = [
                        $type => [
                            $field => $rule['attributes'],
                        ],
                    ];
                }
            } else {
                $array[] = [
                    $type => [
                        $field => $rule['value'],
                    ],
                ];
                
            }
        });
        
        return $array;
    }
    
    /**
     * Add to the musts array.
     *
     * @param string $field
     * @param $value
     * @param array $attributes
     * @return void
     */
    protected function must(string $field, $value, array $attributes = []): void
    {
        $this->musts[$field] = [
            'value'      => $value,
            'attributes' => $attributes,
        ];
    }
    
    /**
     * Add to the mustNots array.
     *
     * @param string $field
     * @param $value
     * @param array $attributes
     * @return void
     */
    protected function mustNot(string $field, $value, array $attributes = []): void
    {
        $this->mustNots[$field] = [
            'value'      => $value,
            'attributes' => $attributes,
        ];
    }
    
    /**
     * @param array $fields
     * @param $value
     * @param array $attributes
     */
    protected function multi_match(array $fields, $value, array $attributes = []): void
    {
        $this->multiMatches[] = [
            'value'      => $value,
            'fields'     => $fields,
            'attributes' => $attributes,
        ];
    }
    
    /**
     * Add the the shoulds array.
     *
     * @param string $field
     * @param $value
     * @param array $attributes
     * @return void
     */
    protected function should(string $field, $value, array $attributes = []): void
    {
        $this->shoulds[$field] = [
            'value'      => $value,
            'attributes' => $attributes,
        ];
    }
    
    /**
     * Add to the filters array.
     *
     * @param string $field
     * @param $value
     * @param array $attributes
     * @return void
     */
    protected function filter(string $field, $value, array $attributes = []): void
    {
        $this->filters[$field] = [
            'value'      => $value,
            'attributes' => $attributes,
        ];
    }
    
    /**
     * Return a range formatted array.
     *
     * @param string $field
     * @param array $values
     * @param string $type
     * @return void
     * @throws Exception
     */
    protected function range(string $field, array $values, string $type): void
    {
        if (count($values) % 2 !== 0) {
            throw new Exception('Range values must be in pairs.');
        }
        
        $this->ranges[$field] = [
            'values' => $values,
            'type'   => $type,
        ];
    }
}
