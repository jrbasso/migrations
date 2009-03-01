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
	 * Constructor
	 */
	function __construct($connection = 'default') {
		$this->db =& ConnectionManager::getDataSource($connection);
	}

    /**
     * Funçao de criaçao de tabela
     */
    function createTable(){}
    /**
     * Funcao de alteraçao de tabela
     */
    function changeTable(){}
    /**
     * Funçao de excluir tabela
     */
    function dropTable(){}
    /**
     * Adicionar colunas
     */
    function addColumn(){}
    /**
     * Remover colunas
     */
    function removeColumn(){}
    /**
     * Alterar colunas
     */
    function changeColumn(){}
    /**
     * Renomear colunas
     */
    function renameColumn(){}
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
			$this->db->begin(null);
			if (!$this->$command()) {
				$this->db->rollback(null);
				$ok = false;
			} else {
				$this->commit(null);
			}
		}
		$method = 'after' . $callback;
		if (method_exists($this, $method)) {
			$this->$method($ok);
		}
		return $ok;
	}
}
?>