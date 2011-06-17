<?php

Class DatasourceFormatHTML extends DatasourceFormat {
	
	public function __construct(Array $input, $engine) {
		parent::__construct($input, $engine);
	}

	protected function __formatSchema() {
		if ($this->_input['source'] instanceof Section) {
			foreach($this->_input['schema'] as $f) {
				$this->_output['schema'][] = array(
					$f['field']->get('label'), 'col',
					array('id' => 'field-' . $f['field']->get('id'), 'class' => 'field-' . $f['field']->get('type'))
				);
			}

			$allExternal = true;
			foreach($this->_input['schema'] as $f) {
				$allExternal = $allExternal && $f['external'];
			}

			if ($allExternal) {
				$temp[0] = array(__('ID'), 'col');
				$this->_output['schema'] = array_merge($temp, $this->_output['schema']);
			}
		}
		else if ($this->_input['source'] == 'authors') {
			foreach($this->_input['schema'] as $f) {
				if ($f == 'username') {
					$this->_output['schema'][] = array(__('Username'), 'col');
				}
				else if ($f == 'name') {
					$this->_output['schema'][] = array(__('Name'), 'col');
				}
				else if ($f == 'email') {
					$this->_output['schema'][] = array(__('Email'), 'col');
				}
				else if ($f == 'author-token') {
					$this->_output['schema'][] = array(__('Author Token'), 'col');
				}
				else if ($f == 'default-area') {
					$this->_output['schema'][] = array(__('Default Area'), 'col');
				}
			}

			if (in_array($this->_output['schema'][0][0], array(__('Author Token'), __('Default Area')))) {
				$temp[0] = array(__('ID'), 'col');
				$this->_output['schema'] = array_merge($temp, $this->_output['schema']);
			}
		}
	}

	protected function __formatRecords() {
		if(!is_array($this->_input['entries']['records']) || empty($this->_input['entries']['records'])){
			return $this->_output['records'] = array(
				Widget::TableRow(array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead))), 'odd')
			);
		}

		if ($this->_input['source'] instanceof Section) {
			foreach($this->_input['entries']['records'] as $entry) {
				$tableData = array();
				$section = $this->_input['source']->get('handle');

				if (count($this->_input['schema']) != count($this->_output['schema'])) { // ID column
					$tableData[] = Widget::TableData(
						Widget::Anchor(
							$entry->get('id'),
							sprintf('%s/publish/%s/edit/%d',
								SYMPHONY_URL, $section, $entry->get('id')
							),
							$entry->get('id'), 'content'
						)->generate()
					);
				}

				foreach($this->_input['schema'] as $position => $f) {
					$data = $entry->getData($f['field']->get('id'));

					if ($position == 0 && (count($this->_input['schema']) == count($this->_output['schema']))) {
						$tableData[] = Widget::TableData(
							Widget::Anchor(
								$f['field']->prepareTableValue($data, NULL, $entry->get('id')),
								sprintf('%s/publish/%s/edit/%d',
									SYMPHONY_URL, $section, $entry->get('id')
								),
								$entry->get('id'), 'content'
							)->generate()
						);
					}
					else if ($f['external']) {
						$search_value = $f['field']->fetchAssociatedEntrySearchValue(
							$entry->getData($f['section']->get('id')),
							$f['section']->get('id'),
							$entry->get('id')
						);

						$tableData[] = Widget::TableData(
							Widget::Anchor(
								sprintf('%d &rarr;', max(0, intval($f['field']->fetchAssociatedEntryCount($search_value)))),
								sprintf('%s/publish/%s/?filter=%s:%s',
									SYMPHONY_URL, $f['section']->get('handle'),
									$f['field']->get('element_name'), rawurlencode($search_value)
								),
								$entry->get('id'), 'content'
							)->generate()
						);
					} else {
						$value = $f['field']->prepareTableValue($data, NULL, $entry->get('id'));
						$tableData[] = Widget::TableData(
							$value, sprintf('field-%s field-%s%s',
								$f['field']->get('type'),
								$f['field']->get('id'),
								($value == __('None')) ? ' inactive' : ''
							)
						);
					}
				}

				$this->_output['records'][] = Widget::TableRow($tableData, NULL, 'id-' . $entry->get('id'));
			}
		}
		else if ($this->_input['source'] == 'authors') {
			foreach($this->_input['entries']['records'] as $author) {
				$tableData = array();
				$values = array();
				$id = $author->getEncapsulatedObject()->get('id');

				if(count($this->_input['schema']) != count($this->_output['schema'])) {
					$values[] = Widget::Anchor(
						$id,
						sprintf('%s/system/authors/edit/%s',
							SYMPHONY_URL, $id
						),
						$id, 'content'
					)->generate();
				}
				if(in_array('username', $this->_input['schema'])) {
					$values[] = $author->getEncapsulatedObject()->get('username');
				}
				if(in_array('name', $this->_input['schema'])) {
					$values[] = $author->getEncapsulatedObject()->getFullName();
				}
				if(in_array('email', $this->_input['schema'])) {
					$values[] = $author->getEncapsulatedObject()->get('email');
				}
				if(in_array('author-token', $this->_input['schema'])) {
					if ($author->getEncapsulatedObject()->isTokenActive()) {
						$values[] = $author->getEncapsulatedObject()->createAuthToken();
					} else {
						$values[] = __('None');
					}
				}
				if(in_array('default-area', $this->_input['schema'])) {
					$section = $author->getDefaultArea();
					
					$values[] = Widget::Anchor(
						$section->get('name'),
						sprintf('%s/publish/%s',
							SYMPHONY_URL, $section->get('handle')
						),
						$id, 'content'
					)->generate();
				}

				if (count($this->_input['schema']) == count($this->_output['schema'])) {
					$values[0] = Widget::Anchor(
						$values[0],
						sprintf('%s/system/authors/edit/%s',
							SYMPHONY_URL, $id
						),
						$id, 'content'
					)->generate();
				}

				foreach($values as $value) {
					$tableData[] = Widget::TableData($value, ($value == __('None')) ? 'inactive' : '');
				}

				$this->_output['records'][] = Widget::TableRow($tableData, NULL, 'id-' . $id);
			}
		}
	}
}
