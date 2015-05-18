<?php

namespace SymphonyCMS\Extensions\Relationships;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Lang;
use PDO;
use SectionManager;
use StdClass;
use Symphony;

class Relationship implements ArrayAccess, IteratorAggregate
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
        if (array_key_exists($name, $this->data)) {
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
            return $this->data;
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

    /**
     * Validate the current value of a given field name
     *
     * @param  string $field
     * @return boolean
     */
    public function validate($field)
    {
        $value = $this->data[$field];

        switch ($field) {
                case 'id':
                case 'min':
                case 'max':
                    return !is_null($value) && is_numeric($value);

                case 'name':
                    return !is_null($value) && is_string($value) && strlen(trim($value)) > 0;

                case 'handle':
                    if (!is_null($value) && is_string($value) && strlen(trim($value)) > 0) {
                        $existing = RelationshipManager::fetchByHandle($value);

                        if (!$existing) {
                            return true;
                        }

                        if (isset($this['id']) && $this['id'] === $existing['id']) {
                            return true;
                        } elseif (!isset($this['id']) && $value !== $existing['handle']) {
                            return true;
                        }
                    }

                    return false;

                case 'sections':
                    return is_array($value) && !empty($value) && count($value) >= 2;
        }

        return false;
    }

    /**
     * Get an iterator for the values in this Relationship
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Does this relationship have an interface field in the given section?
     *
     * @param   integer  $sectionId
     * @return  boolean
     */
    public function hasField($sectionId)
    {
        $hasField = Symphony::Database()->prepare("
            select
                field_id
            from
                tbl_relationships_fields
            where
                relationship_id = :relationship_id
                and section_id = :section_id
            limit 1
        ");
        $hasField->bindValue('relationship_id', $this['id']);
        $hasField->bindValue('section_id', $sectionId);
        $hasField->execute();

        return false !== $hasField->fetch();
    }

    /**
     * Get the field ID linked to the given section.
     *
     * @param   integer  $sectionId
     * @return  integer|boolean
     */
    public function getField($sectionId)
    {
        $getField = Symphony::Database()->prepare("
            select
                field_id
            from
                tbl_relationships_fields
            where
                relationship_id = :relationship_id
                and section_id = :section_id
            limit 1
        ");
        $getField->bindColumn('field_id', $fieldId);
        $getField->bindValue('relationship_id', $this['id']);
        $getField->bindValue('section_id', $sectionId);
        $getField->execute();

        if ($getField->fetch()) {
            return $fieldId;
        }

        return false;
    }

    public function addLink($fromEntryId, $toEntryId)
    {
        // We always insert links with the lowest entry ID
        // on the left for consistency:
        $entries = [$fromEntryId, $toEntryId];
        sort($entries, SORT_NUMERIC);
        list($fromEntryId, $toEntryId) = $entries;

        $linked = Symphony::Database()->prepare("
            select
                relationship_id
            from
                tbl_relationships_entries
            where
                relationship_id = :relationship_id
                and left_entry_id = :left_entry_id
                and right_entry_id = :right_entry_id
            limit 1
        ");
        $linked->bindValue('relationship_id', $this['id']);
        $linked->bindValue('left_entry_id', $fromEntryId);
        $linked->bindValue('right_entry_id', $toEntryId);
        $linked->execute();

        // Link already exists:
        if ($linked->fetch()) {
            return true;
        }

        // Create the link:
        $link = Symphony::Database()->prepare("
            insert into
                tbl_relationships_entries
            set
                relationship_id = :relationship_id,
                left_entry_id = :left_entry_id,
                right_entry_id = :right_entry_id
        ");
        $link->bindValue('relationship_id', $this['id']);
        $link->bindValue('left_entry_id', $fromEntryId);
        $link->bindValue('right_entry_id', $toEntryId);

        return $link->execute();
    }

    public function removeAllLinks($entryId)
    {
        $unlink = Symphony::Database()->prepare("
            delete from
                tbl_relationships_entries
            where
                relationship_id = :relationship_id
                and (
                    left_entry_id = :left_entry_id
                    or right_entry_id = :right_entry_id
                )
        ");
        $unlink->bindValue('relationship_id', $this['id']);
        $unlink->bindValue('left_entry_id', $entryId);
        $unlink->bindValue('right_entry_id', $entryId);

        return $unlink->execute();
    }

    public function getEntries()
    {
        $find = Symphony::Database()->prepare("
            select distinct
                entries.id
            from
                sym_entries as entries,
                sym_relationships_entries as links
            where
                links.relationship_id = :relationship_id
                and (
                    links.left_entry_id = entries.id
                    or links.right_entry_id = entries.id
                )
        ");
        $find->bindValue('relationship_id', $this['id']);
        $find->execute();

        $entries = $find->fetchAll(PDO::FETCH_COLUMN, 0);

        $entries = array_map(function($id) {
            return (integer)$id;
        }, $entries);

        return $entries;
    }

    public function getEntriesByEntryId($entryId)
    {
        $find = Symphony::Database()->prepare("
            select
                case when
                    left_entry_id = :entry_id
                then
                    right_entry_id
                else
                    left_entry_id
                end as id
            from
                sym_relationships_entries
            where
                relationship_id = :relationship_id
                and (
                    left_entry_id = :left_entry_id
                    or right_entry_id = :right_entry_id
                )
        ");
        $find->bindValue('relationship_id', $this['id']);
        $find->bindValue('entry_id', $entryId);
        $find->bindValue('left_entry_id', $entryId);
        $find->bindValue('right_entry_id', $entryId);
        $find->execute();

        $entries = $find->fetchAll(PDO::FETCH_COLUMN, 0);

        $entries = array_map(function($id) {
            return (integer)$id;
        }, $entries);

        return $entries;
    }
}
