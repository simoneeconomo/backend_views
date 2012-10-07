<?php

	require_once(CONTENT . '/content.publish.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');

	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceengine.php');
	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceformat.php');
	require_once(EXTENSIONS . '/backend_views/lib/class.datasourceformatHTML.php');

	class contentExtensionBackend_viewsView extends contentPublish {

		public function __construct(){
			parent::__construct();
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
			$context = $this->getContext();
			$datasources = DatasourceManager::listAll();
			$datasource = self::getDSNameFromHandle($context[0], $datasources);

			if ($datasource === NULL)
				Administration::instance()->customError(
					__('Unknown View'),
					__('The View you are lookin for <code>%s</code> could not be found.', array($context[0]))
				);

			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $datasource)));

			$this->appendSubheading($datasource, array(
				Widget::Anchor(
					__('Create New'),
					URL . '/symphony/blueprints/datasources/new/',
					__('Create a new view'),
					'create button', NULL, array('accesskey' => 'c')),
				Widget::Anchor(
					__('Edit view'),
					URL . '/symphony/blueprints/datasources/edit/' . $context[0],
					__('Edit the current view'),
					'button', NULL, array('accesskey' => 'e'))
			));

			/* -----------------------------------------------------
			 * Fetching
			 * -----------------------------------------------------
			 */

			$result = DatasourceEngine::fetch(
				DatasourceManager::create($this->_context[0], NULL, false),
				Administration::instance(),
				array(
					'startpage' => $_REQUEST['pg']
				)
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

			/* -----------------------------------------------------
			 * Pagination
			 * -----------------------------------------------------
			 */

			$entries = $result['entries'];

			if (isset($entries['total-entries']) && $entries['total-entries'] != count($entries['records'])) {
				$current_page = (isset($_REQUEST['pg']) ? intval($_REQUEST['pg']) : 1);

				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');

				## First
				$li = new XMLElement('li');
				if($current_page > 1)
					$li->appendChild(Widget::Anchor(__('First'), Administration::instance()->getCurrentPageURL(). '?pg=1'));
				else
					$li->setValue(__('First'));
				$ul->appendChild($li);

				## Previous
				$li = new XMLElement('li');
				if($current_page > 1)
					$li->appendChild(Widget::Anchor(__('&larr; Previous'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($current_page - 1)));
				else
					$li->setValue(__('&larr; Previous'));
				$ul->appendChild($li);

				## Summary
				$li = new XMLElement('li', __('Page %1$s of %2$s', array($current_page, max($current_page, $entries['total-pages']))));
				$li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
					$entries['start'],
					($current_page != $entries['total-pages']) ? $current_page * $entries['limit'] : $entries['total-entries'],
					$entries['total-entries']
				)));
				$ul->appendChild($li);

				## Next
				$li = new XMLElement('li');
				if($current_page < $entries['total-pages'])
					$li->appendChild(Widget::Anchor(__('Next &rarr;'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($current_page + 1)));
				else
					$li->setValue(__('Next &rarr;'));
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');
				if($current_page < $entries['total-pages'])
					$li->appendChild(Widget::Anchor(__('Last'), Administration::instance()->getCurrentPageURL(). '?pg=' . $entries['total-pages']));
				else
					$li->setValue(__('Last'));
				$ul->appendChild($li);

				$this->Form->appendChild($ul);
			}
		}

	}

?>
