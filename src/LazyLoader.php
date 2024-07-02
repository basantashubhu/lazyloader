<?php

namespace Basanta\LazyLoader;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Expression;

class LazyLoader
{
    private string $class;
    private ?string $relationAs;
    private $keys = [];
    private $query;
    private $wheres = [];
    private $filter;

    public function __construct(
        private Collection $collection
    ) {}

    static function make(Collection $collection): LazyLoader
    {
        return new static($collection);
    }

    public function load($class, $relationAs = null)
    {
        $this->class = $class;
        $this->relationAs = $relationAs ?: Str::snake(basename($class));
        return $this;
    }

    private function parseKey($keys)
    {
        if(count($keys) == 1) {
            return [$keys[0], $keys[0]];
        }
        return $keys;
    }
    
    public function on($keys)
    {
        if(is_array($keys) && Arr::isAssoc($keys)) {
            $this->keys = array_merge($this->keys, $keys);
        } elseif(is_string($keys)) {
            list($key, $relatedKey) = $this->parseKey(func_get_args());
            $this->keys[$key] = $relatedKey;
        }
        return $this;
    }

    public function where(...$wheres)
    {
        $this->wheres[] = $wheres;
        return $this;
    }

    private function createQuery()
    {
        $query = $this->class::query();
        foreach($this->collection as $i => $model) {
            if(isset($this->filter)) {
                $filter = $this->filter;
                if(!$filter($model, false)) {
                    continue;
                }
            }
            foreach($this->keys as $relatedKey => $key) {
                if(is_array($key)) {
                    $in[$i][$relatedKey] = array_map(fn($k) => $k instanceof Expression ? $k : Arr::get($model, $k), $key);
                    continue;
                }
                if($key instanceof Expression) {
                    $in[$i][$relatedKey] = $key;
                    continue;
                }
                $in[$i][$relatedKey] = Arr::get($model, $key);
            }
            $wh = $in[$i];
            $fl = array_filter($in[$i], fn($v) => is_array($v) ? !empty(array_filter($v, fn($v1) => $v1 instanceof Expression ? false : $v1)) : !empty($v));
            if(empty($fl)) {
                continue;
            }
            $query->orWhere(function($query) use($wh) {
                foreach($wh as $key => $value) {
                    $whereFunc = is_array($value) ? 'whereIn' : 'where';
                    $query->$whereFunc($key, $value);
                }
            });
        }
        $this->query = $query;
    }

    private function fetch($fields, $whereFunc)
    {
        $this->createQuery();
        $fetchEligible = strcasecmp(to_sql($this->query, false), to_sql($this->class::query(), false)) != 0;
        foreach($this->wheres as $wheres) {
            $this->query->where(...$wheres);
        }
        $results = !$fetchEligible ? collect([]) : $this->query->get(array_merge($fields, array_keys($this->keys)));
        return $this->collection->map(function($model)use($results, $whereFunc) {
            if(isset($this->filter)) {
                $filter = $this->filter;
                if(!$filter($model, true)) {
                    $model[$this->relationAs] ??= $whereFunc == 'firstWhere' ? null : [];
                    return $model;
                }
            }
            $related = $results->$whereFunc(function($related) use($model) {
                foreach($this->keys as $relatedKey => $key) {
                    if(is_array($key)) {
                        $modelKeys = array_map(fn($k) => $k instanceof Expression ? strtolower(json_decode($k->getValue($this->query->getGrammar()))) : strtolower(Arr::get($model, $k)), $key);
                        $relatedKey = str_replace($related->getTable().'.', '', $relatedKey);
                        if(!in_array(strtolower($related->$relatedKey), $modelKeys)) {
                            return false;
                        }
                        continue;
                    }
                    if($key instanceof Expression) {
                        continue;
                    }
                    $modelKey = Arr::get($model, $key);
                    $relatedKey = str_replace($related->getTable().'.', '', $relatedKey);
                    if(strcasecmp($modelKey, $related->$relatedKey) !== 0) {
                        return false;
                    }
                }
                return true;
            });
            $model[$this->relationAs] = $related?->toArray();
            return $model;
        });
    }

    public function when(callable $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    public function single($fields = ['*'])
    {
        return $this->fetch($fields, 'firstWhere');
    }

    public function multi($fields = ['*'])
    {
        return $this->fetch($fields, 'where');
    }
}
