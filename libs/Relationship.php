<?php

namespace SymphonyCMS\Extensions\Relationships;

use ArrayAccess;
use Lang;
use PDO;
use SectionManager;
use StdClass;
use Symphony;

class Relationship implements ArrayAccess
{
    /**
     * An array of the Relationship's settings
     * @var array
     */
    protected $data = [
        'id' =>         null,
        'name' =>       null,
        'handle' =>     null,
        'min' =>        0,
        'max' =>        0,
        'sections' =>   []
    ];

    public function offsetGet($name)
    {
        return (
            isset($this->data[$name])
                ? $this->data[$name]
                : null
        );
    }

    public function offsetSet($name, $value)
    {
        switch ($name) {
            case 'id':
            case 'min':
            case 'max':
                $value = (integer)$value;
                break;

            case 'name':
                if (false === isset($this['handle']) || '' === trim($this['handle'])) {
                    $this['handle'] = $value;
                }
                break;

            case 'handle':
                $value = Lang::createHandle($value);
                break;

            case 'sections':
                if (is_array($value)) {
                    $value = array_map(function($value) {
                        return (integer)$value;
                    }, $value);
                    $value = array_filter($value, function($value) {
                        return $value > 0;
                    });
                }

                else if ((integer)$value > 0) {
                    $value = [(integer)$value];
                }

                else {
                    $value = [];
                }
                break;
        }

        $this->data[$name] = $value;
    }

    public function offsetExists($name)
    {
        return isset($this->data[$name]);
    }

    public function offsetUnset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * A setter function that will save a section's setting into
     * the poorly named `$this->_data` variable
     *
     * @deprecated
     *  Access the objects properties as an array $obj['id'].
     * @param string $setting
     *  The setting name
     * @param string $value
     *  The setting value
     */
    public function set($setting, $value)
    {
        $this[$setting] = $value;
    }

    /**
     * An accessor function for this Relationship's settings. If the
     * $setting param is omitted, an array of all settings will
     * be returned. Otherwise it will return the data for
     * the setting given.
     *
     * @deprecated
     *  Access the objects properties as an array $obj['id'].
     * @param null|string $setting
     * @return array|string
     *    If setting is provided, returns a string, if setting is omitted
     *    returns an associative array of this Relationship's settings
     */
    public function get($setting = null)
    {
        if (is_null($setting)) {
            return (array)$this;
        }

        return $this[$setting];
    }

    /**
     * Returns any sections in this Relationship.
     *
     * @return array
     */
    public function fetchSections()
    {
        if (empty($this['sections'])) {
            return [];
        }

        return SectionManager::fetch($this['sections']);
    }
}