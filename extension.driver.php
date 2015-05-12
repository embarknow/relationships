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

    public function install()
    {
        /*
        create table `sym_relationships` (
            `id` int(11) unsigned not null auto_increment,
            `name` varchar(255) default null,
            `min` tinyint(4) not null default '0',
            `max` tinyint(4) not null default '0',
            primary key (`id`)
        );

        create table `sym_relationships_sections` (
            `id` int(11) unsigned not null auto_increment,
            `relationship_id` int(11) default null,
            `section_id` int(11) default null,
            primary key (`id`),
            unique key `section_to_relationship` (`relationship_id`, `section_id`)
        );
        */
    }

    public function uninstall()
    {
        // Remove the tables...
    }
}