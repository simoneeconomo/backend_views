<?php

	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.widget.php');

	Class extension_backend_views extends Extension {

		public function about() {
			return array(
				'name'			=> 'Backend Views',
				'version'		=> '0.1',
				'release-date'	=> '2011-06-12',
				'author' => array('name' => 'Simone Economo',
					'website' => 'http://www.lineheight.net',
					'email' => 'my.ekoes@gmail.com'),
				'description'	=> 'A different way to filter entries in the backend + the power of Data Sources, all in a single extension'
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

		public function fetchNavigation(){
			$navigation = array(
				'location' => 190,
				'name' => __('Views'),
				'children' => array()
			);

			$active_views = Symphony::Configuration()->get('active_views', 'backend_views');
			$active_views = explode(",", $active_views);

			if(!is_array($active_views) || empty($active_views)) return array();

			$datasources = new DatasourceManager($this->_Parent);
			$datasources = $datasources->listAll();

			foreach($datasources as $d) {
				if (in_array($d['handle'], $active_views)) {
					$navigation['children'][] = array(
						'link' => '/view/' . $d['handle'],
						'name' => $d['name']
					);
				}
			}

			return array($navigation);
		}

		public function addViewsPreferences($context) {
			$dsManager = new DatasourceManager(Administration::instance());
			$datasources = $dsManager->listAll();

			$fieldset = new XMLElement('fieldset', NULL, array('class' => 'settings'));
			$fieldset->appendChild(new XMLElement('legend', __('Views')));
			$context['wrapper']->appendChild($fieldset);

			$active_views = Symphony::Configuration()->get('active_views', 'backend_views');
			$active_views = explode(",", $active_views);

			$options = array();
			foreach($datasources as $d) {
				if (substr($d['type'], -3) != 'xml' || $d['type'] == 'navigation')
					$options[] = array($d['handle'], @in_array($d['handle'], $active_views), $d['name']);
			}

			$selectbox = Widget::Select('settings[backend_views][active_views][]', $options, array('multiple' => true));
			$label = Widget::Label(__('Active views'), $selectbox);
			$fieldset->appendChild($label);
		}

		public function saveViews($context) {
			if (empty($context['settings']['backend_views']['active_views']))
				$context['settings']['backend_views']['active_views'] = NULL;
			else
				$context['settings']['backend_views']['active_views'] = implode(",", $context['settings']['backend_views']['active_views']);
		}

		public function uninstall() {
			Symphony::Configuration()->remove('backend_views');
			Administration::instance()->saveConfig();
		}

	}

?>
