<?php

class Extension_Relationships extends Extension
{
    public function getSubscribedDelegates()
    {
        return [
            [
                'page' =>       '*',
                'delegate' =>   'ComposerReady',
                'callback' =>   'onComposerReady'
            ]
        ];
    }

    public function onComposerReady($context)
    {
        $context['autoloader']->setPsr4('SymphonyCMS\\Extensions\\Relationships\\', __DIR__ . '/libs');
    }

    public function fetchNavigation() {
        return array(
            array(
                'location'  => __('Blueprints'),
                'name'      => __('Relationships'),
                'link'      => '/relationships/'
            )
        );
    }

    public function install()
    {
        $relations = Symphony::Database()->prepare("
            create table if not exists `tbl_relationships` (
                `id` int(11) unsigned not null auto_increment,
                `name` varchar(255) default null,
                `handle` varchar(255) default null,
                `min` tinyint(4) not null default '0',
                `max` tinyint(4) not null default '0',
                primary key (`id`)
            )
        ");
        $entries = Symphony::Database()->prepare("
            create table if not exists `tbl_relationships_entries` (
                `id` int(11) unsigned not null auto_increment,
                `relationship_id` int(11) default null,
                `left_entry_id` int(11) default null,
                `right_entry_id` int(11) default null,
                primary key (`id`),
                unique key `entry_to_entry_to_relationship` (`relationship_id`, `left_entry_id`, `right_entry_id`)
            )
        ");
        $fields = Symphony::Database()->prepare("
            create table if not exists `tbl_relationships_fields` (
                `id` int(11) unsigned not null auto_increment,
                `relationship_id` int(11) default null,
                `section_id` int(11) default null,
                `field_id` int(11) default null,
                primary key (`id`),
                unique key `field_to_section_to_relationship` (`relationship_id`, `section_id`, `field_id`),
                key `section_to_relationship` (`relationship_id`, `section_id`)
            )
        ");
        $sections = Symphony::Database()->prepare("
            create table if not exists `tbl_relationships_sections` (
                `id` int(11) unsigned not null auto_increment,
                `relationship_id` int(11) default null,
                `section_id` int(11) default null,
                primary key (`id`),
                unique key `section_to_relationship` (`relationship_id`, `section_id`)
            )
        ");

        try {
            return (
                $relations->execute()
                && $entries->execute()
                && $fields->execute()
                && $sections->execute()
            );
        }

        catch (DatabaseException $error) {
            Symphony::Log()->pushExceptionToLog($error, true);

            return false;
        }
    }

    public function uninstall()
    {
        $relations = Symphony::Database()->prepare("
            drop table `sym_relationships`
        ");
        $sections = Symphony::Database()->prepare("
            drop table `sym_relationships_sections`
        ");

        try {
            return $relations->execute() && $sections->execute();
        }

        catch (DatabaseException $error) {
            Symphony::Log()->pushExceptionToLog($error, true);

            return false;
        }
    }
}