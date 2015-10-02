<?php
namespace Flow\JSONPath;

use ArrayAccess;
use Iterator;
use JsonSerializable;
use Closure;
use Flow\JsonPath\Filters\AbstractFilter;

class JSONPath implements ArrayAccess, Iterator, JsonSerializable
{
    protected static $tokenCache = [];

    protected $data;

    protected $options;

    const ALLOW_MAGIC = 1;

    /**
     * @param $data
     * @param int $options
     */
    public function __construct(& $data, $options = 0)
    {
        $this->data =& $data;
        $this->options = $options;
    }

    /**
     * @param $expression
     * @param Closure|null $callback
     * @return static
     */
    public function find($expression)
    {
        $tokens = $this->parseTokens($expression);

        $collectionData = [$this->data];

        foreach ($tokens as $token) {
            $filter = $token->buildFilter($this->options);

            $filteredData = [];

            foreach ($collectionData as $value) {
                if (AccessHelper::isCollectionType($value)) {
                    $filteredValue = $filter->filter($value);

                    foreach ($filteredValue as $v) {
                        $filteredData[] = $v;
                    }
                }
            }

            $collectionData = $filteredData;
        }

        return new static($collectionData, $this->options);
    }

    /**
     * @param $expression
     * @param Closure|null $callback
     * @return static
     */
    public function modify($expression, Closure $callback = null)
    {
        $tokens = $this->parseTokens($expression);

        $collectionData = [];

        $collectionData[0] =& $this->data;

        foreach ($tokens as $token) {
            $filter = $token->buildFilter($this->options);

            $filteredData = [];

            foreach ($collectionData as & $value) {
                if (AccessHelper::isCollectionType($value)) {
                    $filteredValue = $filter->filter($value);

                    foreach ($filteredValue as & $v) {
                        $filteredData[] =& $v;
                    }
                }
            }

            $collectionData = $filteredData;
        }

        if ($callback) {
            foreach ($collectionData as $i => &$item) {
                $callback($item, $i);
            }
        }

        return new static($collectionData, $this->options);
    }

    /**
     * @return mixed
     */
    public function first()
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        $value = isset($this->data[$keys[0]]) ? $this->data[$keys[0]] : null;

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * Evaluate an expression and return the last result
     * @return mixed
     */
    public function last()
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        $value = $this->data[end($keys)] ? $this->data[end($keys)] : null;

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * @param $expression
     * @return array
     * @throws \Exception
     */
    public function parseTokens($expression)
    {
        $cacheKey = md5($expression);

        if (isset(static::$tokenCache[$cacheKey])) {
            return static::$tokenCache[$cacheKey];
        }

        $lexer = new JSONPathLexer($expression);

        $tokens = $lexer->parseExpression();

        static::$tokenCache[$cacheKey] = $tokens;

        return $tokens;
    }

    /**
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    public function offsetExists($offset)
    {
        return AccessHelper::keyExists($this->data, $offset);
    }

    public function offsetGet($offset)
    {
        $value = AccessHelper::getValue($this->data, $offset);

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            AccessHelper::setValue($this->data, $offset, $value);
        }
    }

    public function offsetUnset($offset)
    {
        AccessHelper::unsetValue($this->data, $offset);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Return the current element
     */
    public function current()
    {
        $value = current($this->data);

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        next($this->data);
    }

    /**
     * Return the key of the current element
     */
    public function key()
    {
        return key($this->data);
    }

    /**
     * Checks if current position is valid
     */
    public function valid()
    {
        return key($this->data) !== null;
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        reset($this->data);
    }

    /**
     * @param $key
     * @return JSONPath|mixed|null|static
     */
    public function __get($key)
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : null;
    }

}
