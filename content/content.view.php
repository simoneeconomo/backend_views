<?php

	require_once(CONTENT . '/content.publish.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');

	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceengine.php');
	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceformat.php');
	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceformatHTML.php');

	class contentExtensionBackend_viewsView extends contentPublish {

		public function __construct(&$parent){
			parent::__construct($parent);
		}

		private static function getDSNameFromHandle($handle, $datasources) {
			foreach($datasources as $d) {
				if ($d['handle'] == $handle) {
					return $d['name'];
				}
			}
			return NULL;
		}

		public function view(){
			$datasources = new DatasourceManager(Administration::instance());
			$datasource = self::getDSNameFromHandle($this->_context[0], $datasources->listAll());

			if ($datasource === NULL)
				Administration::instance()->customError(
					__('Unknown View'),
					__('The View you are lookin for <code>%s</code> could not be found.', array($this->_context[0]))
				);

			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $datasource)));

			$this->appendSubheading($datasource,
				Widget::Anchor(
					__('Create New'),
					URL . '/symphony/blueprints/datasources/new/',
					__('Create a new view'),
					'create button', NULL, array('accesskey' => 'c'))->generate() . ' ' .
				Widget::Anchor(
					__('Edit view'),
					URL . '/symphony/blueprints/datasources/edit/' . $this->_context[0],
					__('Edit the current view'),
					'button', NULL, array('accesskey' => 'e'))->generate()
			);

			$result = DatasourceEngine::fetch(
				$datasources->create($this->_context[0], NULL, false),
				Administration::instance()
			);

			$format = new DatasourceFormatHTML($result, $engine);
			$schema = $format->getFormattedSchema();
			$records = $format->getFormattedRecords();

			$table = Widget::Table(
				Widget::TableHead($schema),
				NULL,
				Widget::TableBody($records)
			);

			$this->Form->appendChild($table);
		}

	}

?>
