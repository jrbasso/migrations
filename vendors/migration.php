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
	 * Constructor
	 */
	function __construct($connection = 'default', &$shell = null) {
		$this->_db =& ConnectionManager::getDataSource($connection);
		$this->_db->cacheSources = false;
		$this->_shell =& $shell;
		$this->__fakeSchema = new CakeSchema();

		// Uses
		$uses = get_class_vars('AppMigration');
		$uses = $uses['uses'];
		if (!is_array($uses)) {
			$uses = array();
		}
		if (!is_array($this->uses)) {
			$this->uses = array($this->uses);
		}
		$uses = array_unique(array_merge($uses, $this->uses));
		foreach ($uses as $use) {
			if (!PHP5) {
				$this->{$use} =& ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $connection));
			} else {
				$this->{$use} = ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $connection));
			}
			if (!$this->{$use}) {
				$this->_shell->err(String::insert(__d('migrations', 'Model ":model" not exists.', true), array('model' => $use)));
				exit();
			}
		}
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
    function createTable($tableName, $columns, $indexes = array()) {
		$this->out('> ' . String::insert(__d('migrations', 'Creating table ":table"... ', true), array('table' => $tableName)), false);
		$this->__fakeSchema->tables = array($tableName => $columns);
		if (is_array($indexes) && !empty($indexes)) {
			$this->__fakeSchema->tables['indexes'] = $indexes;
		}
		if ($this->_db->execute($this->_db->createSchema($this->__fakeSchema))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
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
		$this->out('> ' . String::insert(__d('migrations', 'Dropping table ":table"... ', true), array('table' => $tableName)), false);
		$this->__fakeSchema->tables = array($tableName => '');
		if ($this->_db->execute($this->_db->dropSchema($this->__fakeSchema, $tableName))) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
		return false;
	}

    /**
     * Adicionar colunas
     */
    function addColumn($tableName, $columnName, $columnConfig = array()) {
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
		return false;
	}

    /**
     * Remover colunas
     */
    function removeColumn($tableName, $columnName) {
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
		return false;
	}

    /**
     * Alterar colunas
     */
    function changeColumn($tableName, $columnName, $newColumnConfig = array(), $verbose = true) {
		$verbose && $this->out('> ' . String::insert(__d('migrations', 'Changing column ":column"... ', true), array('column' => $columnName)), false);
		if ($this->_db->isInterfaceSupported('describe')) {
			$describe = $this->_db->describe($tableName, true);
			if (!isset($describe[$columnName])) {
				$verbose &&  $this->out(__d('migrations', 'column not found.', true));
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
		return false;
	}

    /**
     * Renomear colunas
     */
    function renameColumn($tableName, $oldColumnName, $newColumnName) {
		$this->out('> ' . String::insert(__d('migrations', 'Renaming column ":old" to ":new"...', true), array('old' => $oldColumnName, 'new' => $newColumnName)), false);
		if ($this->changeColumn($tableName, $oldColumnName, array('name' => $newColumnName), false)) {
			$this->out('ok');
			return true;
		}
		$this->out('nok');
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
		$method = 'before' . $callback;
		if (method_exists($this, $method)) {
			if (!$this->$method()) {
				return false;
			}
		}
		$ok = true;
		if (method_exists($this, $command)) {
			$this->_db->begin($this->__fakeSchema);
			if (!$this->$command()) {
				$this->_db->rollback($this->__fakeSchema);
				$ok = false;
			} else {
				$this->_db->commit($this->__fakeSchema);
			}
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