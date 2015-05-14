<?php

namespace SymphonyCMS\Extensions\Relationships;

use PDO;
use Symphony;

class RelationshipManager
{
    /**
     * Add a Relationship to the database.
     *
     * The Relationship will be assigned a new ID, even if it already
     * has an ID. This allows for a Relationship to be cloned.
     *
     * @param Relationship $relationship
     * @throws DatabaseException
     * @return boolean
     */
    public static function add(Relationship $relationship)
    {
        // Reserve a new ID:
        $reserve = Symphony::Database()->prepare("
            insert into
                `tbl_relationships`
            set
                name = null
        ");
        $reserve->execute();

        $relationship['id'] = Symphony::Database()->getInsertID();

        return self::edit($relationship);
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
     * Returns a new Relationship object filled with the given data.
     *
     * @param   integer $id
     * @param   string  $name
     * @param   string  $handle
     * @param   integer $min
     * @param   integer $max
     * @return Relationship
     */
    public static function createFromTableRow($id, $name, $handle, $min, $max)
    {
        $obj = new Relationship();
        $obj['id'] = $id;
        $obj['name'] = $name;
        $obj['handle'] = $handle;
        $obj['min'] = $min;
        $obj['max'] = $max;

        $findSections = Symphony::Database()->prepare("
            select
                rel.section_id
            from
                `tbl_relationships_sections` as `rel`
            where
                rel.relationship_id = :relationship_id
            order by
                rel.relationship_id ASC
        ");
        $findSections->bindValue(':relationship_id', $id);
        $findSections->execute();

        $obj['sections'] = $findSections->fetchAll(PDO::FETCH_COLUMN, 0);

        return $obj;
    }

    /**
     * Deletes a Relationship from the database/
     *
     * @param Relationship $relationship
     * @throws DatabaseException
     * @return boolean
     */
    public static function delete(Relationship $relationship)
    {
        $relations = Symphony::Database()->prepare("
            delete from
                `tbl_relationships`
            where
                id = :id
        ");
        $relations->bindValue(':id', $relationship['id']);

        $sections = Symphony::Database()->prepare("
            delete from
                `tbl_relationships_sections`
            where
                relationship_id = :relationship_id
        ");
        $sections->bindValue(':relationship_id', $relationship['id']);

        // TODO: Do a transaction around this:
        $relations->execute();
        $sections->execute();

        return true;
    }

    /**
     * Saves an existing Relationship to the database.
     *
     * @param Relationship $relationship
     * @throws DatabaseException
     * @return boolean
     */
    public static function edit(Relationship $relationship)
    {
        // Insert or replace the Relationship:
        $edit = Symphony::Database()->prepare("
            replace into
                `tbl_relationships`
            set
                id = :id,
                name = :name,
                handle = :handle,
                min = :min,
                max = :max
        ");
        $edit->bindValue(':id', $relationship['id']);
        $edit->bindValue(':name', $relationship['name']);
        $edit->bindValue(':handle', $relationship['handle']);
        $edit->bindValue(':min', $relationship['min']);
        $edit->bindValue(':max', $relationship['max']);

        // Clear section relationships:
        $clear = Symphony::Database()->prepare("
            delete from
                `tbl_relationships_sections`
            where
                relationship_id = :relationship_id
        ");
        $clear->bindValue(':relationship_id', $relationship['id']);

        // Insert section relationships:
        $insertQuery = null;
        $insertValues = [];

        foreach ($relationship['sections'] as $index => $sectionId) {
            $insertValues[] = $relationship['id'];
            $insertValues[] = $sectionId;

            if ($index) {
                $insertQuery .= ",\n\t\t";
            }

            $insertQuery .= '(null, ?, ?)';
        }

        $insert = Symphony::Database()->prepare("
            insert into
                `tbl_relationships_sections`
            values
                {$insertQuery}
        ");

        foreach ($insertValues as $index => $value) {
            $insert->bindValue(1 + $index, $value);
        }

        // TODO: Do a transaction around this:
        $edit->execute();
        $clear->execute();
        $insert->execute();

        return true;
    }

    /**
     * Returns an array of Relationships, sortable by providing a sort order
     * and sort column. By default, Relationships will be ordered in ascending
     * order by their name.
     *
     * @param array $relationshipIds
     *    An array of ID's.
     * @param string $sortorder
     *    Defaults to `asc`, allows `asc` or `desc`.
     * @param string $sortcolumn
     *    Defaults to `name`, allows `id`, `name` or `handle`.
     * @throws DatabaseException
     * @return array
     *    An array of Relationship objects
     */
    public static function fetch(array $relationshipIds = null, $sortorder = 'asc', $sortcolumn = 'name')
    {
        $results = [];

        $sortorder = (
            in_array($sortorder, ['asc', 'desc'])
                ? $sortorder
                : 'asc'
        );

        $sortcolumn = (
            in_array($sortcolumn, ['id', 'name', 'handle'])
                ? $sortcolumn
                : 'name'
        );

        if (empty($relationshipIds)) {
            $statement = Symphony::Database()->prepare("
                select
                    rel.*
                from
                    `tbl_relationships` as `rel`
                order by
                    rel.`{$sortcolumn}` {$sortorder}
            ");
            $statement->execute();
        }

        else {
            $inQuery = implode(',', array_fill(0, count($relationshipIds), '?'));
            $statement = Symphony::Database()->prepare("
                select
                    rel.*
                from
                    `tbl_relationships` as `rel`
                where
                    rel.id in ({$inQuery})
                order by
                    rel.`{$sortcolumn}` {$sortorder}
            ");

            foreach ($relationshipIds as $index => $relationshipId) {
                $statement->bindValue(1 + $index, $relationshipId);
            }

            $statement->execute();
        }

        $statement->fetchAll(PDO::FETCH_FUNC, function(...$row) use (&$results) {
            $results[] = self::createFromTableRow(...$row);
        });

        return $results;
    }

    /**
     * Returns a Relationship by its ID.
     *
     * @param array $relationshipId
     * @throws DatabaseException
     * @return boolean
     */
    public static function fetchById($relationshipId)
    {
        $find = Symphony::Database()->prepare("
            select
                rel.*
            from
                `tbl_relationships` as `rel`
            where
                rel.id = :id
            limit
                1
        ");
        $find->bindValue(':id', $relationshipId);
        $find->execute();

        $row = $find->fetch(PDO::FETCH_ASSOC);

        if (false === $row) {
            return false;
        }

        return self::createFromTableRow(...array_values($row));
    }

    /**
     * Returns any Relationships that belong to a Section.
     *
     * @param integer $section_id
     *    The ID of the section
     * @return array
     */
    public static function fetchBySectionId($sectionId)
    {
        $statement = Symphony::Database()->prepare('
            select
                rel.relationship_id
            from
                `tbl_relationships_sections` as `rel`
            where
                rel.section_id = :section_id
        ');
        $statement->bindValue(':section_id', $sectionId);
        $statement->execute();

        $relationshipIds = $statement->fetchAll(PDO::FETCH_COLUMN, 0);

        if (empty($relationshipIds)) {
            return [];
        }

        return self::fetch($relationshipIds);
    }
}