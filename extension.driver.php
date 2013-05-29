<?php

	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.widget.php');

	define("BACKEND_VIEW_NAME_MISSING", 1);
	define("BACKEND_VIEW_GROUP_MISSING", 2);
	define("BACKEND_VIEW_DATASOURNE_MISSING", 4);

	Class extension_backend_views extends Extension {

		public function about() {
			return array(
				'name'			=> 'Backend Views',
				'version'		=> '0.1.2',
				'release-date'	=> '2012-10-08',
				'author' => array('name' => 'Simone Economo',
					'website' => 'http://www.lineheight.net',
					'email' => 'my.ekoes@gmail.com'),
				'description'	=> 'Use Data Sources to filter entries in the Symphony backend.'
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'addViewsPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'saveViews'
				)
			);
		}
		
		public function fetchNavigation() {
			$backend_views_settings = self::loadAllViewSettings();

			if (!$backend_views_settings) return;

			$navigation_to_add = array();
			$existing_navigation_groups = self::getExisitingNavigationGroups();
			$groups_needing_to_be_created = array();
			$datasources = DatasourceManager::listAll();

			// find which navigation groups need to be created
			foreach ($backend_views_settings as $key => $settings) {
				if ( !in_array( $settings['group'], $existing_navigation_groups ) ) {
					$groups_needing_to_be_created[] = $settings['group'];
				} 
			}
			$groups_needing_to_be_created = array_unique($groups_needing_to_be_created);

			// Add the everything which does not need a new group
			foreach ($backend_views_settings  as $key => $settings) {
				if ( !in_array( $settings['group'], $groups_needing_to_be_created) ) {
					$navigation_to_add[] = array( 'name' => $settings['name'], 'link' => '/view/' . $settings['datasource'], 'location' => $settings['group'] );
				}
			}

			// create these navigation groups
			foreach ($groups_needing_to_be_created as $group_needed) {
				// create the group
				$new_group = array(
					'location' => 300,
					'name' => __($group_needed),
					'children' => array()
				);
				// add each item
				// could be optimi[s|z]ed|z]ed!
				foreach ( $backend_views_settings  as $key => $settings ) { 
					if ( in_array( $settings['group'], $groups_needing_to_be_created) ) {
						$new_group['children'][] = array( 'name' => $settings['name'], 'link' => '/view/' . $settings['datasource'], 'location' => $settings['group'] );
					}
				}
				$navigation_to_add[] = $new_group;
			}

			return $navigation_to_add;
		}
		public function createBackendViewTemplate($settings="-1", $position="-1") {
			$is_template = ($settings=="-1");

			// general template settings
			$li = new XMLElement('li');
			$classes = "";
			if ($is_template ) {
				$classes = 'template';
			} else if ($settings['error_mask'] != 0) {
 				$classes = "instance collapsed";
			} else {
				$classes = "instance";
			}

			$li->setAttribute('class', $classes);
			$li->setAttribute('data-type', 'New View');
		
			// Header
			$header = new XMLElement('header', NULL, array('data-name' => $dataSourceName));
			$label = $is_template ? __('New view') : $settings['name']; 
			$header->appendChild(new XMLElement('h4', '<strong>' . $label . '</strong> <span class="type">' . $modes[$mode] . '</span>'));
			$li->appendChild($header);

			// Name label
			$label = Widget::Label(__("View name") . '<i>' . __('Name in dropdown') . '</i>', null, 'column');
			$label->appendChild(Widget::Input("backend_views[{$position}][name]", $is_template ? "" : $settings['name'], 'text', array('data-view-name'=>'true')));
			// Add error message
			if ( (!$is_template) && ($settings['error_mask'] & BACKEND_VIEW_NAME_MISSING) ) {
				$error = Widget::Error( $label, __('This is a required field.') );
				$label = $error;
			}
			$li->appendChild($label);

			// Data sources
			$datasources = DatasourceManager::listAll();
			$options = array();
			foreach($datasources as $d) {
				if (substr($d['type'], -3) != 'xml' || $d['type'] == 'navigation')
					$options[] = array($d['handle'], (!$is_template && $d['handle'] == $settings['datasource']), $d['name']);
			}
			$selectbox = Widget::Select("backend_views[{$position}][datasource]", $options, array('multiple' => false));
			$label = Widget::Label(__('Datasource'), $selectbox);
			// Add error message
			if ( (!$is_template) && ($settings['error_mask'] & BACKEND_VIEW_DATASOURNE_MISSING) ) {
				$error = Widget::Error( $label, __('This is a required field.') );
				$label = $error;
			}
			$li->appendChild($label);

			// Section name
			// get all the navigation groups
			$navigation_groups = self::getExisitingNavigationGroups();
			$navigation_groups_string = implode($navigation_groups, ", ");

			$label = Widget::Label(__("Navigation group") . "<i>{$navigation_groups_string} or new group</i>", null, 'column');
			$label->appendChild(Widget::Input("backend_views[{$position}][group]", $is_template ? $navigation_groups[0] : $settings['group'], 'text'));
			// Add error message
			if ( (!$is_template) && ($settings['error_mask'] & BACKEND_VIEW_GROUP_MISSING) ) {
				$error = Widget::Error( $label, __('This is a required field.') );
				$label = $error;
			}
			$li->appendChild($label);

			return $li;
		}

		public function addViewsPreferences($context) {
			// Create the field set
			$fieldset = new XMLElement('fieldset', NULL, array('class' => 'settings'));
			$fieldset->appendChild(new XMLElement('legend', __('Backend Views')));
			$context['wrapper']->appendChild($fieldset);

			// JavaScript for view duplicator
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/backend_views/assets/backend_views.preferences.js', 3134);

			$fieldset->appendChild(new XMLElement('p', __('Views'), array('class' => 'label')));
			$div = new XMLElement('div', null, array('class' => 'frame'));
			$duplicator = new XMLElement('ol');
			$duplicator->setAttribute('class', 'views-duplicator');
			$duplicator->setAttribute('data-add', __('Add backend view'));
			$duplicator->setAttribute('data-remove', __('Remove backend view'));
			$fieldset->appendChild($duplicator);

			// add a template to the duplicator
			$duplicator->appendChild(self::createBackendViewTemplate());

			// get all the saved settings
			$view_settings = self::loadAllViewSettings();

			if (sizeof($view_settings) > 0) { 
				foreach ($view_settings as $position => $view_setting) {
					$duplicator->appendChild(self::createBackendViewTemplate($view_setting, $position));
				}
			}

			return;
		}

		public function validateViewSettings(&$viewSettings) {
			$error_mask = 0;
			if ($viewSettings['name'] == '') {
				$error_mask |= BACKEND_VIEW_NAME_MISSING;
			}
			if ($viewSettings['group'] == '') {
				$error_mask |= BACKEND_VIEW_GROUP_MISSING;
			}
			if ($viewSettings['datasource'] == '') {
				$error_mask |= BACKEND_VIEW_DATASOURNE_MISSING;
			}
			$viewSettings['error_mask'] = $error_mask;
		}

		private static function sortNavigationGroups($a, $b) {
       		return ($a->get("sortorder") > $b->get("sortorder"));
		}

		public function getExisitingNavigationGroups() {
			$sections = SectionManager::fetch();
			usort($sections, array('extension_backend_views', 'sortNavigationGroups'));
			foreach ($sections as $key => $section) {
			 	$navigation_groups[] = $section->get('navigation_group');
			}
			return array_unique($navigation_groups);
		}

		//! @todo: change to store with mysql? this dirties the config file
		public function saveAllViewSettings($view_settings) {
			// serialize the child elements
			foreach ($view_settings as $key => $settings) {
				Symphony::Configuration()->set($key, serialize($settings), 'backend_views');
			} 
		}

		//! @todo: change to read with mysql? 
		public function loadAllViewSettings() {
			$view_settings = Symphony::Configuration()->get('backend_views');
			if (sizeof($view_settings) > 0) { 
				// unserialize
				foreach ($view_settings as &$view_setting) {
					$view_setting = unserialize($view_setting);
				}
			}
			return $view_settings;
		}

		public function saveViews($context) {
			// First delete them all
			Symphony::Configuration()->remove('backend_views');

			// Add all new
			if ( isset($_POST['backend_views']) ) { 
				$backend_views = $_POST['backend_views'];
				foreach($backend_views as &$view_settings) {
					self::validateViewSettings($view_settings);
				}
				self::saveAllViewSettings($backend_views);
			}
		}

		public function uninstall() {
			Symphony::Configuration()->remove('backend_views');
			Administration::instance()->saveConfig();
		}

	}

?>
