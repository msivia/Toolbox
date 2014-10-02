<?php

namespace Depotwarehouse\Toolbox;


use Depotwarehouse\Toolbox\Exceptions\InvalidArgumentException;
use Depotwarehouse\Toolbox\Exceptions\ParameterRequiredException;

class Verification {

    /**
     * Requires that a set of attributes are present and set on an array
     * @param array $array Array of items
     * @param array $attributes The attributes which must be set on the array
     * @throws Exceptions\ParameterRequiredException
     */
    static function require_set(array $array, array $attributes) {
        foreach ($attributes as $attribute) {
            if (!array_key_exists($attribute, $array) || is_null($array[$attribute]) || (is_string($array[$attribute])  && $array[$attribute] == "")) {
                throw new ParameterRequiredException($attribute);
            }
        }
    }


    /**
     * Filters an array based on its keys starting with a string
     * @param array $array The array of key => values to be filtered
     * @param string $pattern A string representing the start of the desired key(s)
     * @return array The array filtered by key
     */
    static function array_filter_starts_with(array $array, $pattern) {
        $results = array();
        array_walk($array, function ($value, $key) use ($pattern, &$results) {
            if (starts_with($key, $pattern)) {
                $results[$key] = $value;
            }
        });

        return $results;
    }

    /**
     * Filters an array by removing all null values and their keys
     * @param array $array
     * @return array The array with only non-null values contained
     */
    static function array_filter_null(array $array) {
        return array_filter($array, "self::is_not_null");
    }

    /**
     * Checks that the value is not null.
     * @param mixed $var Value to check
     * @return bool Was the value null?
     */
    private static function is_not_null($var) {
        return !is_null($var);
    }
} 