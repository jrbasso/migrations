<?php
/**
 * Classe mae que as migraçoes irao herdar
 */
class Migration {

	/**
	 * Uses models
	 */
	var $uses = array();

	/**
	 * Stop up/down on error
	 */
	var $stopOnError = true;

	/**
	 * DataSource link
	 */
	var $_db = null;

	/**
	 * Schell that called this class
	 */
	var $_shell;

	/**
	 * Fake CakeSchema
	 */
	var $__fakeSchema = null;

	/**
	 * Error
	 */
	var $__error = false;

	/**
	 * Constructor
	 */
	function __construct( &$shell = null) {
		$this->_shell =& $shell;
		$this->_db =& $shell->db;
		$this->__fakeSchema = new CakeSchema();

		$uses = $this->_getUses();

		foreach ($uses as $use) {
			if (!PHP5) {
				$this->{$use} =& ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $shell->connection));
			} else {
				$this->{$use} = ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $shell->connection));
			}
			if (!$this->{$use}) {
				$this->_shell->err(String::insert(
					__d('migrations', 'Model ":model" not exists.', true),
					array('model' => $use)
				));
				$this->_shell->_stop();
			}
		}
	}
	function _getUses(){
		// Uses
		$uses = get_class_vars('AppMigration');
		$uses = $uses['uses'];
		if (!is_array($uses)) {
			$uses = array();
		}
		if (!is_array($this->uses)) {
			$this->uses = array($this->uses);
		}
		return array_unique(array_merge($uses, $this->uses));
	}
	/**
	 * Pega o model relativo a tabela
	 */
	function getModel($tableName) {
		if (!in_array($tableName, $this->_db->listSources())) {
			return null;
		}
		return new Model(array('name' => Inflector::camelize(Inflector::singularize($tableName)), 'table' => $tableName, 'ds' => $this->_db->configKeyName));
	}

    /**
     * Funçao de criaçao de tabela
     */
    function createTable($tableName, $columns, $indexes = array(), $options = array()) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}

		$_defaults = array(
			'insertId' => true,
			'insertCreated' => true,
			'insertModified' => true,
			'insertUpdated' => false
		);
		$options = array_merge($_defaults, $options);

		if (!empty($options['insertId']) && !isset($columns['id'])) {
			$idColumn = array(
				'id' => array(
					'type' => 'integer',
					'null' => false,
					'default' => null,
					'key' => 'primary'
				)
			);
			$columns = $idColumn + $columns; // To insert on the begin
		}
		$datesColumn = array(
			'type' => 'datetime',
			'null' => false,
			'default' => null
		);
		foreach (array('created', 'modified', 'updated') as $field) {
			if ($options['insert' . Inflector::camelize($field)] && !isset($columns[$field])) {
				$columns[$field] = $datesColumn;
			}
		}

		$this->out('> ' . String::insert(
			__d('migrations', 'Creating table ":table"... ', true),
			array('table' => $tableName)
		), false);
		$this->__fakeSchema->tables = array($tableName => $columns);
		if (is_array($indexes) && !empty($indexes)) {
			$this->__fakeSchema->tables['indexes'] = $indexes;
		}
		if ($this->_db->execute($this->_db->createSchema($this->__fakeSchema))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Funcao de alteraçao de tabela
     */
    function changeTable(){}

    /**
     * Funçao de excluir tabela
     */
    function dropTable($tableName) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		if (is_array($tableName)) {
			foreach ($tableName as $table) {
				if (!$this->dropTable($table) && $this->stopOnError && $this->__error) {
					return false;
				}
			}
			return true;
		}
		$this->out('> ' . String::insert(__d('migrations', 'Dropping table ":table"... ', true), array('table' => $tableName)), false);
		$this->__fakeSchema->tables = array($tableName => '');
		if ($this->_db->execute($this->_db->dropSchema($this->__fakeSchema, $tableName))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Adicionar colunas
     */
    function addColumn($tableName, $columnName, $columnConfig = array()) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		$columnConfig = array_merge(array('type' => 'integer'), $columnConfig);
		$this->out('> ' . String::insert(__d('migrations', 'Creating column ":column"... ', true), array('column' => $columnName)), false);
		if ($this->_db->execute($this->_db->alterSchema(array(
			$tableName => array(
				'add' => array(
					$columnName => $columnConfig
				)
			)
		), $tableName))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Remover colunas
     */
    function removeColumn($tableName, $columnName) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		$this->out('> ' . String::insert(__d('migrations', 'Removing column ":column"... ', true), array('column' => $columnName)), false);
		if ($this->_db->execute($this->_db->alterSchema(array(
			$tableName => array(
				'drop' => array(
					$columnName => array()
				)
			)
		), $tableName))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Alterar colunas
     */
    function changeColumn($tableName, $columnName, $newColumnConfig = array(), $verbose = true) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		$verbose && $this->out('> ' . String::insert(__d('migrations', 'Changing column ":column"... ', true), array('column' => $columnName)), false);
		if ($this->_db->isInterfaceSupported('describe')) {
			$describe = $this->_db->describe($tableName, true);
			if (!isset($describe[$columnName])) {
				$verbose &&  $this->out(__d('migrations', 'column not found.', true));
				$this->__error = true;
				return false;
			}
			$newColumnConfig = array_merge($describe[$columnName], $newColumnConfig);
		}
		if ($this->_db->execute($this->_db->alterSchema(array(
			$tableName => array(
				'change' => array(
					$columnName => $newColumnConfig
				)
			)
		), $tableName))) {
			$verbose && $this->out('ok');
			return true;
		}
		$verbose && $this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Renomear colunas
     */
    function renameColumn($tableName, $oldColumnName, $newColumnName) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		$this->out('> ' . String::insert(__d('migrations', 'Renaming column ":old" to ":new"...', true), array('old' => $oldColumnName, 'new' => $newColumnName)), false);
		if ($this->changeColumn($tableName, $oldColumnName, array('name' => $newColumnName), false)) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		$this->__error = true;
		return false;
	}

    /**
     * Adicionar Index
     */
    function addIndex(){}
    /**
     * Remover Index
     */
    function removeIndex(){}

	/**
	 * Output a message to console
	 */
	function out($message, $newLine = true) {
		if ($this->_shell) {
			$this->_shell->out($message, $newLine);
		}
	}

	/**
	 * Install revision
	 */
	function install() {
		return $this->_exec('up', 'Install');
	}
	/**
	 * Uninstall revision
	 */
	function uninstall() {
		return $this->_exec('down', 'Uninstall');
	}

	/**
	 * Execute Install and Uninstall methods
	 */
	function _exec($command, $callback) {
		$this->__error = false;
		if (!method_exists($this, $command)) {
			$this->out(String::insert(__d('migrations', '> Method ":method" not implemented. Skipping...', true), array('method' => $command)));
			return true;
		}
		$method = 'before' . $callback;
		if (method_exists($this, $method)) {
			if (!$this->$method()) {
				return false;
			}
		}
		$ok = $this->_db->begin($this->__fakeSchema);
		$this->$command();
		if ($this->stopOnError) {
			if ($this->__error) {
				$ok = false;
			}
		}
		if ($ok) {
			$this->_db->commit($this->__fakeSchema);
		} else {
			$this->_db->rollback($this->__fakeSchema);
		}
		$method = 'after' . $callback;
		if (method_exists($this, $method)) {
			$this->$method($ok);
		}
		return $ok;
	}
}

if (!class_exists('CakeSchema')) {
	class CakeSchema {}
}
?>