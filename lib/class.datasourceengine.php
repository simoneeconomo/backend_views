<?php

Class DatasourceEngine {

	public static function fetch(Datasource $ds, $engine) {
		switch($ds->getSource()) {
#			case 'navigation': {
#					return self::__processNavigation($ds, $engine);
#				}
#				break;
			case 'authors': {
					return self::__processAuthors($ds, $engine);
				}
				break;
			default: {
				if (preg_match("/^[0-9]*$/", $ds->getSource())) {
					return self::__processSection($ds, $engine);
				} else {
					throw new DatasourceNotSupportedException;
				}
			}
		}
	}

	private static function __processNavigation($ds, $engine) {

		/* -----------------------------------------------------
		 * Filtering
		 * -----------------------------------------------------
		 */

		$type_sql = $parent_sql = NULL;

		if(trim($ds->dsParamFILTERS['type']) != '') {
			$filter = $ds->dsParamFILTERS['type'];
			$filter_type = $ds->__determineFilterType($ds->dsParamFILTERS['type']);
	
			$types = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
			$types = array_map('trim', $types);
			$types = array_map(array('Datasource', 'removeEscapedCommas'), $types);

			if($filter_type == DS_FILTER_OR) {
				$type_sql = " AND pt.type IN ('" . implode("', '", $types) . "')";
			} else {
				foreach($types as $type) {
					$type_sql = " AND pt.type = '" . $type . "'";
				}
			}
		}

		if(trim($this->dsParamFILTERS['parent']) != '') {
			$parent = $ds->dsParamFILTERS['parent'];
			$parent_paths = preg_split('/,\s*/', $parent, -1, PREG_SPLIT_NO_EMPTY);
			$parent_paths = array_map(create_function('$a', 'return trim($a, " /");'), $parent_paths);

			$parent_sql = (is_array($parent_paths) && !empty($parent_paths) ? " AND p.`path` IN ('".implode("', '", $parent_paths)."')" : null);
		}

		/* -----------------------------------------------------
		 * Query execution & Delegates
		 * -----------------------------------------------------
		 */

		$pages = Symphony::Database()->fetch(sprintf("
				SELECT DISTINCT p.*
				FROM `tbl_pages` AS p
				LEFT JOIN `tbl_pages_types` AS pt ON (p.id = pt.page_id)
				WHERE 1 = 1
				%s
				%s
				ORDER BY p.`sortorder` ASC
			",
			!is_null($parent_sql) ? $parent_sql : " AND p.parent IS NULL ",
			!is_null($type_sql) ? $type_sql : ""
		));

		/* -----------------------------------------------------
		 * Return
		 * -----------------------------------------------------
		 */
		 
		return array(
			'source' => 'navigation',
			'records' => $pages,
			'schema' => NULL,
		);
	}
	
	private static function __processAuthors($ds, $engine) {

		/* -----------------------------------------------------
		 * Filtering
		 * -----------------------------------------------------
		 */

		$author_ids = array();

		if(is_array($ds->dsParamFILTERS) && !empty($ds->dsParamFILTERS)){
			foreach($ds->dsParamFILTERS as $field => $value){
				if(!is_array($value) && trim($value) == '') continue;

				if(!is_array($value)){
					$bits = preg_split('/,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
					$bits = array_map('trim', $bits);
				} else {
					$bits = $value;
				}

				$sql = "SELECT `id` FROM `tbl_authors` WHERE `".$field."` IN ('".implode("', '", $bits)."')";

				$authors = $database->fetchCol('id', $sql);
				$ret = (is_array($authors) && !empty($authors) ? $authors : NULL);

				if(empty($ret)){
					$author_ids = array();
					break;
				}

				if(empty($author_ids)) {
					$author_ids = $ret;
					continue;
				}

				$author_ids = array_intersect($author_ids, $ret);
			}
		}

		/* -----------------------------------------------------
		 * Query execution & Delegates
		 * -----------------------------------------------------
		 */

		if (!empty($author_ids)) {
			$authors = AuthorManager::fetchByID(array_values($author_ids), $ds->dsParamSORT, $ds->dsParamORDER);
		} else {
			$authors = AuthorManager::fetch($ds->dsParamSORT, $ds->dsParamORDER);
		}

		// This feels a bit hackish. Each Author object is encapsulated in a new one that provides some extra methods
		$wrappers = array();
		foreach($authors as $author) {
			$wrappers[] =  new AuthorWrapper($author, $engine);
		}

		/* -----------------------------------------------------
		 * Return
		 * -----------------------------------------------------
		 */

		return array(
			'source' => 'authors',
			'records' => $wrappers,
			'schema' => $ds->dsParamINCLUDEDELEMENTS
		);
	}
	
	private static function __processSection($ds, $engine) {
		$where = $joins = NULL;
		$group = false;

		include_once(TOOLKIT . '/class.entrymanager.php');
		$entryManager = new EntryManager($engine);

		if(!$section = $entryManager->sectionManager->fetch($ds->getSource())){
			$about = $ds->about();
			trigger_error(__('The section associated with the data source <code>%s</code> could not be found.', array($about['name'])), E_USER_ERROR);
		}

		/* -----------------------------------------------------
		 * Filtering
		 * -----------------------------------------------------
		 */

		if(is_array($ds->dsParamFILTERS) && !empty($ds->dsParamFILTERS)){
			$fieldPool = array();

			foreach($ds->dsParamFILTERS as $field_id => $filter){
				if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

				if(!is_array($filter)){
					$filter_type = $ds->__determineFilterType($filter);
					$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
					$value = array_map('trim', $value);
					$value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
				} else {
					$value = $filter;
				}

				if(!isset($fieldPool[$field_id]) || !is_object($fieldPool[$field_id]))
					$fieldPool[$field_id] =& $entryManager->fieldManager->fetch($field_id);

				if($field_id != 'id' && $field_id != 'system:date' && !($fieldPool[$field_id] instanceof Field)){
					throw new Exception(
						__(
							'Error creating field object with id %1$d, for filtering in data source "%2$s". Check this field exists.',
							array($field_id, $ds->dsParamROOTELEMENT)
						)
					);
				}
				else if($field_id == 'id') {
					$where = " AND `e`.id IN ('".implode("', '", $value)."') ";
				}
				else if($field_id == 'system:date') {
					require_once(TOOLKIT . '/fields/field.date.php');
					$date = new fieldDate(Frontend::instance());

					// Create an empty string, we don't care about the Joins, we just want the WHERE clause.
					$empty = "";
					$date->buildDSRetrievalSQL($value, $empty, $where, ($filter_type == DS_FILTER_AND ? true : false));

					$where = preg_replace('/`t\d+`.value/', '`e`.creation_date', $where);
				} else {
					if(!$fieldPool[$field_id]->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false))) {
						$ds->_force_empty_result = true;
						return array();
					}
					if(!$group) $group = $fieldPool[$field_id]->requiresSQLGrouping();
				}
			}
		}
		
		/* -----------------------------------------------------
		 * Sorting
		 * -----------------------------------------------------
		 */
		
		if($ds->dsParamSORT == 'system:id') {
			$entryManager->setFetchSorting('id', $ds->dsParamORDER);
		}
		else if($ds->dsParamSORT == 'system:date') {
			$entryManager->setFetchSorting('date', $ds->dsParamORDER);
		} else {
			$entryManager->setFetchSorting($entryManager->fieldManager->fetchFieldIDFromElementName($ds->dsParamSORT, $ds->getSource()), $ds->dsParamORDER);
		}

		/* -----------------------------------------------------
		 * Grouping & Pagination
		 * -----------------------------------------------------
		 */
		
		if(is_array($ds->dsParamINCLUDEDELEMENTS)) {
			$include_pagination_element = in_array('system:pagination', $ds->dsParamINCLUDEDELEMENTS);
		}

#		$datasource_schema = $ds->dsParamINCLUDEDELEMENTS;

#		if (!is_array($datasource_schema))
#			$datasource_schema = array();

#		if ($ds->dsParamPARAMOUTPUT)
#			$datasource_schema[] = $ds->dsParamPARAMOUTPUT;

#		if ($ds->dsParamGROUP)
#			$datasource_schema[] = $entryManager->fieldManager->fetchHandleFromID($ds->dsParamGROUP);

		if(!isset($ds->dsParamPAGINATERESULTS)) {
			$ds->dsParamPAGINATERESULTS = 'yes';
		}

		/* -----------------------------------------------------
		 * Fields schema
		 * -----------------------------------------------------
		 */

		$fields_schema = array();
		$sectionManager = new SectionManager($engine);
		$section = $sectionManager->fetch($ds->getSource());

		foreach($ds->dsParamINCLUDEDELEMENTS as $field_handle) {
			$id = $entryManager->fieldManager->fetchFieldIDFromElementName($field_handle);
			$fields_schema[] = array(
				'field' => $entryManager->fieldManager->fetch($id),
				'section' => $section,
				'external' => false
			);
		}

		$associated_sections = $section->fetchAssociatedSections(true);
		if(is_array($associated_sections) && !empty($associated_sections)){
			foreach($associated_sections as $as){
				$id = $as['child_section_field_id'];
				$fields_schema[] = array(
					'field' => $entryManager->fieldManager->fetch($id),
					'section' => $sectionManager->fetch($as['child_section_id']),
					'external' => true
				);
			}
		}

		/* -----------------------------------------------------
		 * Query execution & Delegates
		 * -----------------------------------------------------
		 */

		$entries = $entryManager->fetchByPage(
			($ds->dsParamPAGINATERESULTS == 'yes' && $ds->dsParamSTARTPAGE > 0 ? $ds->dsParamSTARTPAGE : 1),
			$ds->getSource(),
			($ds->dsParamPAGINATERESULTS == 'yes' && $ds->dsParamLIMIT >= 0 ? $ds->dsParamLIMIT : NULL),
			$where, $joins, $group,
			(!$include_pagination_element ? true : false),
			true
#			$datasource_schema
		);

		/**
		 * Immediately after building entries allow modification of the Data Source entry list
		 *
		 * @delegate DataSourceEntriesBuilt
		 * @param string $context
		 * '/frontend/'
		 * @param Datasource $datasource
		 * @param array $entries
		 * @param array $filters
		 */
		Symphony::ExtensionManager()->notifyMembers('DataSourceEntriesBuilt', '/frontend/', array(
			'datasource' => &$ds,
			'entries' => &$entries,
			'filters' => $ds->dsParamFILTERS
		));

		/* -----------------------------------------------------
		 * Return
		 * -----------------------------------------------------
		 */

		return array(
			'source' => $section,
			'records' => $entries['records'],
			'schema' => $fields_schema
		);
	}
}

Class AuthorWrapper {

	private $_object;
	private $_engine;

	public function __construct(Author $author, $engine) {
		$this->_object = $author;
		$this->_engine = $engine;
	}

	public function getEncapsulatedObject() {
		return $this->_object;
	}

	public function getDefaultArea() {
		$default_area = $this->_object->get('default_area');
		$sectionManager = new SectionManager($this->_engine);
		
		if ($default_area) {
				$section = $sectionManager->fetch($default_area);
		} else {
			$id = Symphony::Database()->query("
				SELECT `id`
				FROM `tbl_sections`
				ORDER BY `id` ASC
				LIMIT 1
			");
			$section = $sectionManager->fetch($id);
		}

		return (is_array($section)) ? $section[0] : $section;
	}

}
