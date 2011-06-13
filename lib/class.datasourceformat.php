<?php

abstract class DatasourceFormat {

	protected $_input;
	protected $_output;
	protected $_engine;
	
	public function __construct(Array $input, $engine) {
		if (!is_array($input) || empty($input) || !isset($input['source']) || !isset($input['records']) || !isset($input['schema'])) {
			throw new Exception(
				__('Invalid input array. Cannot generate output')
			);
		}
		
		$this->_input = $input;
		$this->_output = array(
			'schema' => NULL,
			'records' => NULL
		);
		$this->_engine = $engine;
	}

	public function getFormattedSchema() {
		if ($this->_output['schema'] == NULL)
			$this->__formatSchema();
		return $this->_output['schema'];
	}
	
	public function getFormattedRecords() {
		if ($this->_output['records'] == NULL)
			$this->__formatRecords();
		return $this->_output['records'];
	}

	protected abstract function __formatSchema();

	protected abstract function __formatRecords();
}
