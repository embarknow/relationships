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
            create table if not exists `sym_relationships` (
                `id` int(11) unsigned not null auto_increment,
                `name` varchar(255) default null,
                `handle` varchar(255) default null,
                `min` tinyint(4) not null default '0',
                `max` tinyint(4) not null default '0',
                primary key (`id`)
            )
        ");
        $sections = Symphony::Database()->prepare("
            create table if not exists `sym_relationships_sections` (
                `id` int(11) unsigned not null auto_increment,
                `relationship_id` int(11) default null,
                `section_id` int(11) default null,
                primary key (`id`),
                unique key `section_to_relationship` (`relationship_id`, `section_id`)
            )
        ");

        try {
            return $relations->execute() && $sections->execute();
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