<?php
/**
 * Base migration class
 *
 * @link          http://github.com/jrbasso/migrations
 * @package       migrations
 * @subpackage    migrations.vendors
 * @since         v 0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Migration
 */
class Migration {

/**
 * Uses models
 *
 * @var array
 * @access public
 */
	var $uses = array();

/**
 * Stop up/down on error
 *
 * @var boolean
 * @access public
 */
	var $stopOnError = true;

/**
 * DataSource link
 *
 * @var object
 * @access protected
 */
	var $_db = null;

/**
 * Schell that called this class
 *
 * @var object
 * @access protected
 */
	var $_shell;

/**
 * Fake CakeSchema
 *
 * @var object
 * @access private
 */
	var $__fakeSchema = null;

/**
 * Error
 *
 * @var boolean
 * @access private
 */
	var $__error = false;

/**
 * Constructor
 */
	function __construct(&$shell = null) {
		$this->_shell =& $shell;
		$this->_db =& $shell->db;
		$this->__fakeSchema = new CakeSchema();

		$uses = $this->_getUses();

		foreach ($uses as $use) {
			$varName = Inflector::camelize($use);
			if (!PHP5) {
				$this->{$varName} =& ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $shell->connection));
			} else {
				$this->{$varName} = ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $shell->connection));
			}
			if (!$this->{$varName}) {
				$this->_shell->err(String::insert(
					__d('migrations', 'Model ":model" not exists.', true),
					array('model' => $use)
				));
				$this->_shell->_stop();
			}
		}
	}

/**
 * Get uses from parent classes
 *
 * @return array
 * @access protected
 */
	function _getUses(){
		// Uses
		$uses = get_class_vars('AppMigration');
		$uses = $uses['uses'];
		if (!is_array($uses)) {
			$uses = (array)$uses;
		}
		if (!is_array($this->uses)) {
			$this->uses = (array)$this->uses;
		}
		return array_unique(array_merge($uses, $this->uses));
	}

/**
 * Get a model from a table name
 *
 * @param string $tableName
 * @return object Model
 * @access public
 */
	function getModel($tableName) {
		if (!in_array($tableName, $this->_db->listSources())) {
			return null;
		}
		return new AppModel(array('name' => Inflector::camelize(Inflector::singularize($tableName)), 'table' => $tableName, 'ds' => $this->_db->configKeyName));
	}

/**
 * Create a table
 *
 * @param string $tableName
 * @param array $columns
 * @param array $indexes
 * @param array $options
 * @return boolean
 * @access public
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
 * Change some itens from table
 *
 * @todo Need implements. Cake core dont support this
 * @return void
 * @access public
 */
	function changeTable() {
	}

/**
 * Drop a table
 *
 * @param string $tableName Name of table
 * @return boolean
 * @access public
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
 * Include new column
 *
 * @param string $tableName
 * @param string $columnName
 * @param array $columnConfig
 * @return boolean
 * @access public
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
 * Remove a column
 *
 * @param string $tableName
 * @param string $columnName
 * @return boolean
 * @access public
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
 * Change column
 *
 * @param string $tableName
 * @param string $columnName
 * @param array $newColumnConfig
 * @param boolean $verbose
 * @return boolean
 * @access public
 */
	function changeColumn($tableName, $columnName, $newColumnConfig = array(), $verbose = true) {
		if ($this->stopOnError && $this->__error) {
			return false;
		}
		$verbose && $this->out('> ' . String::insert(__d('migrations', 'Changing column ":column"... ', true), array('column' => $columnName)), false);
		if ($this->_db->isInterfaceSupported('describe')) {
			$describe = $this->_db->describe($tableName, true);
			if (!isset($describe[$columnName])) {
				$verbose && $this->out(__d('migrations', 'column not found.', true));
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
 * Rename a column
 *
 * @param string $tableName
 * @param string $oldColumnName
 * @param string $newColumnName
 * @return boolean
 * @access public
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
 * Add new index
 *
 * @todo Need implement.
 * @return void
 * @access public
 */
	function addIndex() {
	}

/**
 * Remove a index
 *
 * @todo Need implement.
 * @return void
 * @access public
 */
	function removeIndex() {
	}

/**
 * Output a message to console
 *
 * @param string $message
 * @param boolean $newLine
 * @return void
 * @access public
 */
	function out($message, $newLine = true) {
		if ($this->_shell) {
			$this->_shell->out($message, $newLine);
		}
	}

/**
 * Install revision
 *
 * @return boolean
 * @access public
 */
	function install() {
		return $this->_exec('up', 'Install');
	}

/**
 * Uninstall revision
 *
 * @return boolean
 * @access public
 */
	function uninstall() {
		return $this->_exec('down', 'Uninstall');
	}

/**
 * Execute Install and Uninstall methods
 *
 * @param string $command Can be 'up' or 'down'
 * @param string $callback Name of callback function
 * @return boolean
 * @access protected
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
