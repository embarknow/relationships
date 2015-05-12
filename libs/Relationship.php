<?php

namespace SymphonyCMS\Extensions\Relationships;

use MySQL;
use PDO;
use SectionManager;
use StdClass;

class Relationship
{
    /**
     * An array of the Relationship's settings
     * @var array
     */
    protected $data = array();

    /**
     * A setter function that will save a section's setting into
     * the poorly named `$this->_data` variable
     *
     * @param string $setting
     *  The setting name
     * @param string $value
     *  The setting value
     */
    public function set($setting, $value)
    {
        $this->data[$setting] = $value;
    }

    /**
     * An accessor function for this Relationship's settings. If the
     * $setting param is omitted, an array of all settings will
     * be returned. Otherwise it will return the data for
     * the setting given.
     *
     * @param null|string $setting
     * @return array|string
     *    If setting is provided, returns a string, if setting is omitted
     *    returns an associative array of this Relationship's settings
     */
    public function get($setting = null)
    {
        if (is_null($setting)) {
            return $this->data;
        }

        return $this->data[$setting];
    }

    /**
     * Returns any sections in this Relationship.
     *
     * @return array
     */
    public function fetchSections()
    {
        $statement = MySQL::getConnectionResource()->prepare('
            select
                rs.section_id
            from
                `sym_relationships_sections` as `rs`
            where
                rs.relationship_id = ?
        ');
        $statement->bindValue(1, $this->get('id'));

        if ($statement->execute()) {
            return SectionManager::fetch($statement->fetchAll(PDO::FETCH_COLUMN, 0));
        }

        return array();
    }
}