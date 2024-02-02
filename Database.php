<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private $skipValue;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->skipValue = new \stdClass(); // Unique value to represent skipped parameters
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $formatted = $query;
        foreach ($args as $key => $value) {
            if ($value === $this->skipValue) {
                continue; 
            }
            if (is_null($value)) {
                $replacement = 'NULL';
            } elseif (is_bool($value)) {
                $replacement = $value ? '1' : '0';
            } elseif (is_int($value)) {
                $replacement = (string)$value;
            } elseif (is_float($value)) {
                $replacement = (string)$value; 
            } elseif (is_string($value)) {
                $replacement = "'" . $this->mysqli->real_escape_string($value) . "'";
            } elseif (is_array($value)) {
                $replacement = $this->formatArray($value, $key); 
            } else {
                throw new Exception('Unsupported value type: ' . gettype($value));
            }
            $formatted = str_replace('?' . $key, $replacement, $formatted);
        }
        return $formatted;
    }

    public function skip()
    {
        return $this->skipValue;
    }

    private function formatValue($value, $specifier)
    {
        switch ($specifier) {
            case 'd':
                return (int)$value;
            case 'f':
                return (float)$value;
            case 'a':
                return $this->formatArray($value, $specifier);
            case '#':
                if (is_array($value)) {
                    return implode(',', array_map(function ($id) {
                        return '`' . $this->mysqli->real_escape_string($id) . '`';
                    }, $value));
                } else {
                    return '`' . $this->mysqli->real_escape_string($value) . '`';
                }
            default:
                if (is_null($value)) {
                    return 'NULL';
                } elseif (is_bool($value)) {
                    return $value ? '1' : '0';
                } elseif (is_int($value) || is_float($value)) {
                    return $value;
                } elseif (is_string($value)) {
                    return "'" . $this->mysqli->real_escape_string($value) . "'";
                } else {
                    throw new Exception('Invalid value type: ' . gettype($value));
                }
        }
    }

    private function formatArray($array, $specifier) {
        $formatted = [];
        foreach ($array as $value) {
            $formatted[] = $this->formatValue($value, $specifier);
        }
        return implode(', ', $formatted);
    }
}
