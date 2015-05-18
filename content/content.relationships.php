<?php

use SymphonyCMS\Extensions\Relationships\RelationshipManager;
use SymphonyCMS\Extensions\Relationships\Relationship;

class contentExtensionRelationshipsRelationships extends AdministrationPage
{
    public $_errors = [];

    public function getUrl($path = null)
    {
        $url = SYMPHONY_URL . '/extension/relationships/relationships/';

        if ($path && trim($path, '/')) {
            $url .= trim($path, '/') . '/';
        }

        return $url;
    }

    public function __viewIndex()
    {
        $this->setPageType('table');
        $this->setTitle(__('%1$s &ndash; %2$s', [__('Relationships'), __('Symphony')]));
        $this->appendSubheading(__('Relationships'), Widget::Anchor(__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create a relationship'), 'create button', null, ['accesskey' => 'c']));

        $relationships = RelationshipManager::fetch();

        $tableHead = array(
            array(__('Name'), 'col'),
            array(__('Sections'), 'col'),
            array(__('Minimum'), 'col'),
            array(__('Maximum'), 'col')
        );

        $tableBody = [];

        if (!is_array($relationships) || empty($relationships)) {
            $tableBody = [
                Widget::TableRow([Widget::TableData(__('None found.'), 'inactive', null, count($tableHead))], 'odd')
            ];
        } else {
            foreach ($relationships as $relationship) {
                $tableBody[] = $this->buildRelationshipRow($relationship);
            }
        }

        $table = Widget::Table(
            Widget::TableHead($tableHead),
            null,
            Widget::TableBody($tableBody),
            'selectable',
            null,
            ['role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive']
        );

        $this->Form->appendChild($table);

        $version = new XMLElement('p', 'Symphony ' . Symphony::Configuration()->get('version', 'symphony'), [
            'id' => 'version'
        ]);

        $this->Form->appendChild($version);

        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');

        $options = [
            [null, false, __('With Selected...')],
            ['delete', false, __('Delete'), 'confirm', null, [
                'data-message' => __('Are you sure you want to delete the selected sections?')
            ]]
        ];

        /**
         * Allows an extension to modify the existing options for this page's
         * With Selected menu. If the `$options` parameter is an empty array,
         * the 'With Selected' menu will not be rendered.
         *
         * @delegate AddCustomActions
         * @since Symphony 2.3.2
         * @param string $context
         * '/blueprints/sections/'
         * @param array $options
         *  An array of arrays, where each child array represents an option
         *  in the With Selected menu. Options should follow the same format
         *  expected by `Widget::__SelectBuildOption`. Passed by reference.
         */
        Symphony::ExtensionManager()->notifyMembers('AddCustomActions', '/blueprints/sections/', array(
            'options' => &$options
        ));

        if (!empty($options)) {
            $tableActions->appendChild(Widget::Apply($options));
            $this->Form->appendChild($tableActions);
        }
    }

    public function __viewNew()
    {
        $relationship = RelationshipManager::create(
            isset($_POST['fields']) && is_array($_POST['fields'])
            ? $_POST['fields']
            : array()
        );

        $this->setPageType('form');
        $this->setTitle(__('%1$s &ndash; %2$s', array(__('Relationships'), __('Symphony'))));
        $this->appendSubheading(__('Untitled'));
        $this->insertBreadcrumbs(array(
            Widget::Anchor(__('Relationships'), SYMPHONY_URL . '/extension/relationships/relationships/'),
        ));

        $formHasErrors = (
            is_array($this->_errors) && !empty($this->_errors)
        );

        if ($formHasErrors) {
            $this->pageAlert(
                __('An error occurred while processing this form. See below for details.'),
                Alert::ERROR
            );
        }

        $this->addRelationshipEssentials($relationship);
        $this->addRelationshipConstraints($relationship);
        $this->addRelationshipSections($relationship);

        $div = new XMLElement('div');
        $div->setAttribute('class', 'actions');
        $div->appendChild(Widget::Input('action[save]', __('Create Relationship'), 'submit', array('accesskey' => 's')));

        $this->Form->appendChild($div);
    }

    public function __viewEdit()
    {
        $relationship_id = $this->_context[1];

        if (!$relationship = RelationshipManager::fetchById($relationship_id)) {
            Administration::instance()->throwCustomError(
                __('The Relationship, %s, could not be found.', array($relationship_id)),
                __('Unknown Relationship'),
                Page::HTTP_STATUS_NOT_FOUND
            );
        }

        if (isset($_POST['fields'])) {
            if (is_array($_POST['fields']) && !empty($_POST['fields'])) {
                foreach ($_POST['fields'] as $field => $value) {
                    $relationship[$field] = $value;
                }
            }
        }

        $this->setPageType('form');
        $this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array($relationship['name'], __('Relationships'), __('Symphony'))));
        $this->appendSubheading($relationship['name']);
        $this->insertBreadcrumbs(array(
            Widget::Anchor(__('Relationships'), $this->getUrl()),
        ));

        $formHasErrors = (
            is_array($this->_errors) && !empty($this->_errors)
        );

        if ($formHasErrors) {
            $this->pageAlert(
                __('An error occurred while processing this form. See below for details.'),
                Alert::ERROR
            );
        } elseif (isset($this->_context[2])) {
            $time = Widget::Time();

            switch ($this->_context[2]) {
                case 'saved':
                    $message = __('Relationship updated at %s.', array($time->generate()));
                    break;
                case 'created':
                    $message = __('Relationship created at %s.', array($time->generate()));
            }

            $this->pageAlert(
                $message
                . ' <a href="' . $this->getUrl('/new') . '" accesskey="c">'
                . __('Create another?')
                . '</a> <a href="' . $this->getUrl() . '" accesskey="a">'
                . __('View all Relationships')
                . '</a>',
                Alert::SUCCESS
            );
        }

        $this->addRelationshipEssentials($relationship);
        $this->addRelationshipConstraints($relationship);
        $this->addRelationshipSections($relationship);

        $div = new XMLElement('div');
        $div->setAttribute('class', 'actions');
        $div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

        $button = new XMLElement('button', __('Delete'));
        $button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this Relationship'), 'type' => 'submit', 'accesskey' => 'd', 'data-message' => __('Are you sure you want to delete this relationship?')));
        $div->appendChild($button);

        $this->Form->appendChild($div);
    }

    public function __actionIndex()
    {
        $checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

        if (is_array($checked) && !empty($checked)) {
            /**
             * Extensions can listen for any custom actions that were added
             * through `AddCustomPreferenceFieldsets` or `AddCustomActions`
             * delegates.
             *
             * @delegate CustomActions
             * @since Symphony 2.3.2
             * @param string $context
             *  '/blueprints/sections/'
             * @param array $checked
             *  An array of the selected rows. The value is usually the ID of the
             *  the associated object.
             */
            Symphony::ExtensionManager()->notifyMembers('CustomActions', '/blueprints/sections/', array(
                'checked' => $checked
            ));

            if ($_POST['with-selected'] === 'delete') {
                $this->performDelete($checked);
            }

            redirect(SYMPHONY_URL . '/extension/relationships/relationships/');
        }
    }

    public function __actionNew()
    {
        $actions = (
            isset($_POST['action'])
            ? array_keys($_POST['action'])
            : array()
        );

        if (in_array('save', $actions) || in_array('done', $actions)) {
            $this->_errors = array();
            $relationship = null;

            $relationship = RelationshipManager::create(
                isset($_POST['fields']) && !empty($_POST['fields'])
                ? $_POST['fields']
                : []
            );

            // Ensure handle is created
            $relationship['handle'] = Lang::createHandle(
                isset($relationship['handle']) && !empty($relationship['handle'])
                ? $relationship['handle']
                : $relationship['name']
            );

            // Validate all the fields
            $canProceed = $this->validateFields($relationship);

            if ($canProceed) {
                $this->performCreate($relationship);

                redirect(sprintf(
                    "%s/extension/relationships/relationships/edit/%s/created/",
                    SYMPHONY_URL,
                    $relationship->get('id')
                ));
            }

            $_POST['fields'] = $relationship->get();
        }
    }

    public function __actionEdit()
    {
        $actions = (
            isset($_POST['action'])
            ? array_keys($_POST['action'])
            : array()
        );
        $relationshipId = $this->_context[1];

        if (in_array('save', $actions) || in_array('done', $actions)) {
            $this->_errors = array();
            $canProceed = true;

            $relationship = RelationshipManager::fetchById($relationshipId);

            if (isset($_POST['fields']) && !empty($_POST['fields'])) {
                foreach ($_POST['fields'] as $field => $value) {
                    $relationship[$field] = $value;
                }
            }

            // Ensure handle is created
            $relationship['handle'] = Lang::createHandle(
                isset($relationship['handle']) && !empty($relationship['handle'])
                ? $relationship['handle']
                : $relationship['name']
            );

            // Validate all the fields
            $canProceed = $this->validateFields($relationship);

            if ($canProceed) {
                $this->performEdit($relationship);

                redirect(sprintf(
                    "%s/extension/relationships/relationships/edit/%s/saved/",
                    SYMPHONY_URL,
                    $relationship->get('id')
                ));
            }

            $_POST['fields'] = $relationship->get();
        }

        if (in_array('delete', $actions)) {
            $items = [$relationshipId];

            $this->performDelete($items);
        }
    }

    protected function buildRelationshipRow(Relationship $relationship)
    {
        // Name Column
        $name = Widget::TableData(Widget::Anchor(
            $relationship['name'],
            Administration::instance()->getCurrentPageURL() . "edit/{$relationship['id']}/",
            null,
            'content'
        ));
        $name->appendChild(Widget::Label(
            __('Select Section %s', array($relationship['name'])),
            null,
            'accessible',
            null,
            array(
                'for' => "relationship-{$relationship['id']}"
            )
        ));
        $name->appendChild(Widget::Input(
            "items[{$relationship['id']}]",
            'on',
            'checkbox',
            array(
                'id' => "relationship-{$relationship['id']}"
            )
        ));

        // Sections Links Column
        $sections = new XMLElement('td');
        $output = '';

        foreach ($relationship->fetchSections() as $section) {
            $link = Widget::Anchor(
                $section->get('name'),
                sprintf(
                    "%s/publish/%s/",
                    SYMPHONY_URL,
                    $section->get('handle')
                )
            );
            $output .= $link->generate() . ', ';
        }

        $sections->setValue(trim($output, ', '));

        // Minimum Column
        if (0 == $relationship['min']) {
            $minimum = Widget::TableData('None');
            $minimum->setAttribute('class', 'inactive');
        } else {
            $minimum = Widget::TableData((string)$relationship['min']);
        }

        // Maximum Column
        if (0 == $relationship->get('max')) {
            $maximum = Widget::TableData('Unlimited');
            $maximum->setAttribute('class', 'inactive');
        } else {
            $maximum = Widget::TableData((string)$relationship->get('max'));
        }

        return Widget::TableRow([
            $name,
            $sections,
            $minimum,
            $maximum
        ]);
    }

    protected function addRelationshipEssentials(Relationship $relationship = null)
    {
        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings');
        $fieldset->appendChild(new XMLElement('legend', __('Essentials')));

        $namediv = new XMLElement('div', null, array('class' => 'column'));

        $label = Widget::Label(__('Name'));
        $label->appendChild(Widget::Input('fields[name]', (isset($relationship['name']) ? General::sanitize($relationship['name']) : null)));

        if (isset($this->_errors['name'])) {
            $namediv->appendChild(Widget::Error($label, $this->_errors['name']));
        } else {
            $namediv->appendChild($label);
        }

        $fieldset->appendChild($namediv);

        $handlediv = new XMLElement('div', null, array('class' => 'column'));

        $label = Widget::Label(__('Handle'));
        $label->appendChild(Widget::Input('fields[handle]', (isset($relationship['handle']) ? General::sanitize($relationship['handle']) : null)));

        if (isset($this->_errors['handle'])) {
            $handlediv->appendChild(Widget::Error($label, $this->_errors['handle']));
        } else {
            $handlediv->appendChild($label);
        }

        $fieldset->appendChild($handlediv);
        $this->Form->appendChild($fieldset);
    }

    protected function addRelationshipConstraints(Relationship $relationship = null)
    {
        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings');
        $fieldset->appendChild(new XMLElement('legend', __('Constraints')));

        $div = new XMLElement('div', null, array('class' => 'two columns'));

        $mindiv = new XMLElement('div', null, array('class' => 'column'));
        $label = Widget::Label(__('Minimum'));
        $label->appendChild(Widget::Input('fields[min]', (isset($relationship['min']) ? (string)$relationship['min'] : null)));

        if (isset($this->_errors['min'])) {
            $mindiv->appendChild(Widget::Error($label, $this->_errors['min']));
        } else {
            $mindiv->appendChild($label);
        }

        $div->appendChild($mindiv);

        $mindiv = new XMLElement('div', null, array('class' => 'column'));
        $label = Widget::Label(__('Maximum'));
        $label->appendChild(Widget::Input('fields[max]', (isset($relationship['max']) ? (string)$relationship['max'] : null)));

        if (isset($this->_errors['max'])) {
            $mindiv->appendChild(Widget::Error($label, $this->_errors['max']));
        } else {
            $mindiv->appendChild($label);
        }

        $div->appendChild($mindiv);

        $fieldset->appendChild($div);
        $this->Form->appendChild($fieldset);
    }

    protected function addRelationshipSections(Relationship $relationship = null)
    {
        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings');
        $fieldset->appendChild(new XMLElement('legend', __('Sections')));

        $sectionsdiv = new XMLElement('div', null, array('class' => 'column'));

        $label = Widget::Label(__('Related Sections'));

        $sections = SectionManager::fetch(null, 'asc', 'name');
        $options = array();

        foreach ($sections as $section) {
            $options[] = array(
                $section->get('id'),
                (
                    isset($relationship['sections']) && is_array($relationship['sections'])
                    ? in_array($section->get('id'), $relationship['sections'])
                    : false
                ),
                $section->get('name')
            );
        }

        $select = Widget::Select(
            'fields[sections][]',
            $options,
            array(
                'multiple' => 'multiple',
                'size' => count($options)
            )
        );

        $label->appendChild($select);

        if (isset($this->_errors['sections'])) {
            $sectionsdiv->appendChild(Widget::Error($label, $this->_errors['sections']));
        } else {
            $sectionsdiv->appendChild($label);
        }

        $fieldset->appendChild($sectionsdiv);
        $this->Form->appendChild($fieldset);
    }

    protected function validateFields(Relationship $relationship)
    {
        $passed = true;

        if (!$relationship->validate('name')) {
            $this->errors['name'] = __('This is a required field.');
            $passed = false;
        }

        if (!$relationship->validate('handle')) {
            $this->_errors['handle'] = __('A Section with the handle %s already exists', array('<code>' . $relationship['handle'] . '</code>'));
            $passed = false;
        }

        if (!$relationship->validate('min')) {
            $this->_errors['min'] = __('This is a required field. Set to \'0\' for no minimum.');
            $passed = false;
        }

        if (!$relationship->validate('max')) {
            $this->_errors['max'] = __('This is a required field. Set to \'0\' for no maximum.');
            $passed = false;
        }

        if (!$relationship->validate('sections')) {
            $this->_errors['sections'] = __('A minimum of two sections are required.');
            $passed = false;
        }

        return $passed;
    }

    protected function performDelete(array &$items = array())
    {
        /**
         * Just prior to calling the Relationsip Manager's delete function
         *
         * @delegate RelationshipPreDelete
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param array $relationship_ids
         *  An array of Relationship ID's passed by reference
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPreDelete',
            '/extension/relationships/',
            array('relationship_ids' => &$items)
        );

        foreach ($items as $relationship_id) {
            $relationship = RelationshipManager::create([
                'id' => $relationship_id
            ]);
            RelationshipManager::delete($relationship);
        }

        /**
         * Just after calling the Relationship Manager's delete function
         *
         * @delegate RelationshipPostDelete
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param array $relationship_ids
         *  An array of Relationship ID's passed by reference
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPostDelete',
            '/extension/relationships/',
            array('relationship_ids' => $items)
        );

        return true;
    }

    protected function performCreate(Relationship $relationship)
    {
        /**
         * Just prior to creating the Relationship.
         *
         * @delegate RelationshipPreCreate
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param Relationship $relationship
         *  The Relationship
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPreCreate',
            '/extension/relationships/',
            array('relationship' => $relationship)
        );

        if (!RelationshipManager::add($relationship)) {
            $this->pageAlert(__('An unknown database occurred while attempting to create the section.'), Alert::ERROR);

            return false;
        }

        /**
         * After the Relationship has been created.
         *
         * @delegate RelationsipPostCreate
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param Relationship $relationship
         *  The Relationship
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPostCreate',
            '/extension/relationships/',
            array('relationship' => $relationship)
        );

        return true;
    }

    protected function performEdit(Relationship $relationship)
    {
        /**
         * Just prior to updating the Relationship
         *
         * @delegate RelationshipPreEdit
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param Relationship $relationship
         *  The Relationship
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPreEdit',
            '/extension/relationships/',
            array('relationship' => $relationship)
        );

        if (!RelationshipManager::edit($relationship)) {
            $this->pageAlert(__('An unknown database occurred while attempting to create the section.'), Alert::ERROR);

            return false;
        }

        /**
         * After the Relationsip has been updated.
         *
         * @delegate RelationshipPostEdit
         * @since Symphony 3.0.0
         * @param string $context
         * '/extension/relationships/'
         * @param Relationsip $relationship
         *  The Relationship.
         */
        Symphony::ExtensionManager()->notifyMembers(
            'RelationshipPostEdit',
            '/extension/relationships/',
            array('relationship' => $relationship)
        );

        return true;
    }
}
