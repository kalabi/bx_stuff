<?php


namespace Custom;


class CustomEntity
{
    /**
     * @var bool
     */
    protected $ib;
    protected $fields;
    protected $properties;
    protected $filter;
    protected $order;
    protected $debug = false;
    protected $log;
    public $callback;

    /**
     * CustomEntity constructor.
     *
     * @param int $ib
     */
    public function __construct($ib = 0)
    {
        if (!$this->ib) {
            if (!$ib) {
                return false;
            }
            else {
                $this->ib = $ib;
            }
        }

        return $this;
    }

    /**
     *
     */
    protected function clear()
    {
        $this->setFields([]);
        $this->setFilter([]);
        $this->setOrder([]);
        $this->setProperties([]);
    }

    /**
     * @param $name
     * @param $args
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array($this->$name, $args);
    }


    /**
     * @param $debug
     *
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLog()
    {
        if ($this->debug) {
            return $this->log;
        }

        return false;
    }

    /**
     * @param $order
     *
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }


    /**
     * @param $filter
     *
     * @return $this
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }


    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setProperties($properties = [])
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function setFields($fields = [])
    {
        $this->fields = $fields;

        return $this;
    }

}