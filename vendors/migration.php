<?php
/**
 * Classe mae que as migraçoes irao herdar
 */
class Migration {

	/**
	 * DataSource link
	 */
	var $db = null;

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
		$this->db =& ConnectionManager::getDataSource($connection);
		$this->db->cacheSources = false;
		$this->__fakeSchema = new CakeSchema();
		$this->_shell =& $shell;
	}

    /**
     * Funçao de criaçao de tabela
     */
    function createTable($tableName, $columns, $indexes = array()) {
		$this->_shell->out('> ' . sprintf(__d('migrations', 'Creating table "%s"... ', true), $tableName), false);
		$this->__fakeSchema->tables = array($tableName => $columns);
		if (is_array($indexes) && !empty($indexes)) {
			$this->__fakeSchema->tables['indexes'] = $indexes;
		}
		if ($this->db->execute($this->db->createSchema($this->__fakeSchema))) {
			$this->_shell->out('ok');
			return true;
		}
		$this->_shell->out('nok');
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
		$this->_shell->out('> ' . sprintf(__d('migrations', 'Dropping table "%s"... ', true), $tableName), false);
		$this->__fakeSchema->tables = array($tableName => '');
		if ($this->db->execute($this->db->dropSchema($this->__fakeSchema, $tableName))) {
			$this->_shell->out('ok');
			return true;
		}
		$this->_shell->out('nok');
		return false;
	}

    /**
     * Adicionar colunas
     */
    function addColumn($tableName, $columnName, $columnConfig = array()) {
		$columnConfig = array_merge(array('type' => 'integer'), $columnConfig);
		$this->_shell->out('> ' . sprintf(__d('migrations', 'Creating column "%s"... ', true), $columnName), false);
		if ($this->db->execute($this->db->alterSchema(array(
			$tableName => array(
				'add' => array(
					$columnName => $columnConfig
				)
			)
		), $tableName))) {
			$this->_shell->out('ok');
			return true;
		}
		$this->_shell->out('nok');
		return false;
	}

    /**
     * Remover colunas
     */
    function removeColumn($tableName, $columnName) {
		$this->_shell->out('> ' . sprintf(__d('migrations', 'Removing column "%s"... ', true), $columnName), false);
		if ($this->db->execute($this->db->alterSchema(array(
			$tableName => array(
				'drop' => array(
					$columnName => array()
				)
			)
		), $tableName))) {
			$this->_shell->out('ok');
			return true;
		}
		$this->_shell->out('nok');
		return false;
	}

    /**
     * Alterar colunas
     */
    function changeColumn($tableName, $columnName, $newColumnConfig = array(), $verbose = true) {
		$verbose && $this->_shell->out('> ' . sprintf(__d('migrations', 'Changing column "%s"... ', true), $columnName), false);
		if ($this->db->isInterfaceSupported('describe')) {
			$describe = $this->db->describe($tableName, true);
			if (!isset($describe[$columnName])) {
				$verbose &&  $this->_shell->out(__d('migrations', 'column not found.', true));
				return false;
			}
			$newColumnConfig = array_merge($describe[$columnName], $newColumnConfig);
		}
		if ($this->db->execute($this->db->alterSchema(array(
			$tableName => array(
				'change' => array(
					$columnName => $newColumnConfig
				)
			)
		), $tableName))) {
			$verbose && $this->_shell->out('ok');
			return true;
		}
		$verbose && $this->_shell->out('nok');
		return false;
	}

    /**
     * Renomear colunas
     */
    function renameColumn($tableName, $oldColumnName, $newColumnName) {
		$this->_shell->out('> ' . sprintf(__d('migrations', 'Renaming column "%s" to "%s"...', true), $oldColumnName, $newColumnName), false);
		if ($this->changeColumn($tableName, $oldColumnName, array('name' => $newColumnName), false)) {
			$this->_shell->out('ok');
			return true;
		}
		$this->_shell->out('nok');
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
			$this->db->begin($this->__fakeSchema);
			if (!$this->$command()) {
				$this->db->rollback($this->__fakeSchema);
				$ok = false;
			} else {
				$this->db->commit($this->__fakeSchema);
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