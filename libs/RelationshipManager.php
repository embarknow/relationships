<?php

namespace SymphonyCMS\Extensions\Relationships;

use MySQL;
use Symphony;

class RelationshipManager
{
    /**
     * An array of all the objects that the Manager is responsible for.
     *
     * @var array
     *   Defaults to an empty array.
     */
    protected static $_pool = array();

    /**
     * Takes an associative array of Relationship settings and creates a new
     * entry in the `tbl_relationships` table, returning the ID of the Relationship.
     * The ID of the section is generated using auto_increment and returned
     * as the Relationship ID.
     *
     * @param array $settings
     *    An associative of settings for a section with the key being
     *    a column name from `tbl_relationships`
     * @throws DatabaseException
     * @return integer
     *    The newly created Relationship's ID
     */
    public static function add(array $settings)
    {
        if (!Symphony::Database()->insert($settings, 'tbl_relationships')) {
            return false;
        }

        return Symphony::Database()->getInsertID();
    }

    /**
     * Updates an existing Relationship given it's ID and an associative
     * array of settings. The array does not have to contain all the
     * settings for the Relationship as there is no deletion of settings
     * prior to updating the Relationship
     *
     * @param integer $relationship_id
     *    The ID of the Relationship to edit
     * @param array $settings
     *    An associative of settings for a section with the key being
     *    a column name from `tbl_relationships`
     * @throws DatabaseException
     * @return boolean
     */
    public static function edit($relationship_id, array $settings)
    {
        if (!Symphony::Database()->update($settings, 'tbl_relationships', ' `id` = ?', array($relationship_id))) {
            return false;
        }

        return true;
    }

    /**
     * Deletes a Relationship by Relationship ID, removing all entries, fields, the
     * Relationship and any Relationship Associations in that order
     *
     * @param integer $relationship_id
     *    The ID of the Relationship to delete
     * @throws DatabaseException
     * @throws Exception
     * @return boolean
     *    Returns true when completed
     */
    public static function delete($relationship_id)
    {
        // Delete the relationship
        Symphony::Database()->delete('tbl_relationships', "`id` = ?", array($relationship_id));

        // Delete the relationship associations
        Symphony::Database()->delete('tbl_relationships_sections', "`relationship_id` = ?", [
            $relationship_id
        ]);

        return true;
    }

    /**
     * Returns a Relationship object by ID, or returns an array of Sections
     * if the Relationship ID was omitted. If the Relationship ID is omitted, it is
     * possible to sort the Sections by providing a sort order and sort
     * field. By default, Sections will be order in ascending order by
     * their name
     *
     * @param integer|array $relationship_id
     *    The ID of the section to return, or an array of ID's. Defaults to null
     * @param string $order
     *    If `$relationship_id` is omitted, this is the sortorder of the returned
     *    objects. Defaults to ASC, other options id DESC
     * @param string $sortfield
     *    The name of the column in the `tbl_relationships` table to sort
     *    on. Defaults to name
     * @throws DatabaseException
     * @return Relationship|array
     *    A Relationship object or an array of Relationship objects
     */
    public static function fetch($relationship_id = null, $order = 'ASC', $sortfield = 'name')
    {
        $returnSingle = false;
        $relationship_ids = array();

        if (!is_null($relationship_id)) {
            if (!is_array($relationship_id)) {
                $returnSingle = true;
                $relationship_ids = array($relationship_id);
            }

            else {
                $relationship_ids = $relationship_id;
            }
        }

        if ($returnSingle && isset(self::$_pool[$relationship_id])) {
            return self::$_pool[$relationship_id];
        }

        if (!empty($relationship_id)) {
            $placeholders = Database::addPlaceholders($relationship_ids);
            $additional_sql = " WHERE `s`.`id` IN ($placeholders) ";
        }

        else {
            $additional_sql = " ORDER BY `s`.`$sortfield` $order";
        }

        $sql = "SELECT `s`.* FROM `tbl_relationships` AS `s`" . $additional_sql;

        if (!$sections = Symphony::Database()->fetch($sql, null, array(), $relationship_ids)) {
            return ($returnSingle ? false : array());
        }

        $ret = array();

        foreach ($sections as $s) {
            $obj = self::create();

            foreach ($s as $name => $value) {
                $obj->set($name, $value);
            }

            self::$_pool[$obj->get('id')] = $obj;

            $ret[] = $obj;
        }

        return (count($ret) == 1 && $returnSingle ? $ret[0] : $ret);
    }

    /**
     * Returns a new Relationship object.
     *
     * @return Relationship
     */
    public static function create()
    {
        $obj = new Relationship;
        return $obj;
    }

    /**
     * Returns any section relationships this section has with other sections.
     *
     * @param integer $section_id
     *    The ID of the section
     * @return array
     */
    public static function fetchFromSectionId($section_id)
    {
        $statement = MySQL::getConnectionResource()->prepare('
            select
                rs.relationship_id
            from
                `tbl_relationships_sections` as `rs`
            where
                rs.section_id = ?
        ');
        $statement->bindValue(1, $section_id);

        if ($statement->execute()) {
            var_dump($statement->fetchAll());
            var_dump(__FILE__, __LINE__);
            exit;
        }

        return array();
    }
}