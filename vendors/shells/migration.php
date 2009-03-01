<?php

class MigrationShell extends Shell {

	/**
	 * Path to installers files
	 */
	var $path = null;

	/**
	 * Name of database
	 */
	var $connection = 'default';

	/**
	 * DB
	 */
	var $db = null;

	/**
	 * Schema table in database
	 */
	var $_schemaTable = 'schema_migrations';

	/**
	 * Name of plugin directory
	 */
	var $_pluginName = null;

	/**
	 * Startup script
	 */
	function startup() {
		if (empty($this->params['path'])) {
			$this->path = $this->params['working'] . DS . 'config' . DS . 'sql' . DS . 'migrations';
		} else {
			$this->path = $this->params['path'];
		}

		if (!empty($this->params['connection'])) {
			$this->connection = $this->params['connection'];
		}

		$this->_startDBConfig();

		if (preg_match("/\/plugins\/([^\/]+)\/vendors\/shells\/migration\.php$/", $this->Dispatch->shellPath, $matches)) {
            $this->_pluginName = Inflector::camelize($matches[1]) . '.';
        }

		parent::startup();
		$this->out('Migrations Shell');
		$this->hr();
		$this->out('Path to migrations classes: ' . $this->path);
		$this->out('Connection to the database: ' . $this->connection);
		$this->out('Last migration installed: ' . '');
		$this->hr();
	}

	/**
	 * Configs of database
	 */
	function _startDBConfig() {
		config('database');

		App::import('Model', array('ConnectionManager', 'Model'));
		$this->db =& ConnectionManager::getDataSource($this->connection);
		$this->db->cacheSources = false;

		$sources = $this->db->sources();
		if (!is_array($sources)) { // Database connection error
			$this->_stop();
		}
		if (!in_array($this->_schemaTable, $sources)) { // Create table if not exists
			if (!$this->db->execute($this->db->renderStatement('schema', array(
				'table' => $this->_schemaTable,
				'columns' => array(
					$this->db->buildColumn(array('name' => 'id', 'type' => 'integer', 'null' => false, 'default' => NULL)),
					$this->db->buildColumn(array('name' => 'version', 'type' => 'integer', 'null' => true, 'default' => NULL)),
					$this->db->buildColumn(array('name' => 'datetime', 'type' => 'integer', 'null' => true, 'default' => NULL))
				),
				'indexes' => $this->db->buildIndex(array('PRIMARY' => array('column' => 'id', 'unique' => 1)))
			)))) {
				$this->err(sprintf(__d('Migrations', 'Schema table "%s" can not be created.', true), $this->_schemaTable));
				$this->_stop();
			}
		}
		$this->SchemaMigration = new Model(array('name' => 'SchemaMigration', 'table' => $this->_schemaTable, 'ds' => $this->connection));
		$this->_versions = $this->SchemaMigration->find('all');
	}

	/**
	 * Main
	 */
	function main() {
		$this->help();
	}

	/**
	 * Install or update database
	 */
	function up() {
		$this->_exec('install');
	}

	/**
	 * Drop or downgrade database
	 */
	function down() {
		$this->_exec('uninstall');
	}

	/**
	 * Down all migrations
	 */
	function reset() {
	}

	/**
	 * Down all migrations an Up later
	 */
	function rebuild() {
		$this->reset();
		$this->up();
	}

	/**
	 * Check if class and action exists to execute
	 */
	function _exec($action) {
		// Generate $filename and $classname
		App::import('Vendor', $this->_pluginName . 'Migration'); // To not need include in installer file
		include $filename;
		if (!class_exists($classname)) {
			$this->err('The class ' . $classname . ' not in file.');
			$this->_stop();
		}
		$script = new $classname();
		if (!is_subclass_of($script, 'Migration')) {
			$this->err('Class ' . $classname . ' not extends Migration.');
			$this->_stop();
		}
		$ok = $script->$action($this->connection);
		// TODO: Control for revision
	}

	/**
	 * Help
	 */
	function help() {
		$this->out(__d('Migrations', 'Usage: cake migration <command> <arg1> <arg2>...', true));
		$this->hr();
		$this->out(__d('Migrations',
"Params:
	-connection <config>
		set db config <config>. Uses 'default' if none is specified.
	-path <dir>
		path <dir> to read and write migrations scripts.
		default path: " . $this->params['working'] . DS . 'config' . DS . 'sql' . DS . 'migrations', true));
		$this->out(__d('Migrations', 
"Commands:
	migration help
		shows this help message.
	migration up [date]
		upgrade database to specified date. If date not specified, latest is used. Date must be in format YYYYMMDDHHMMSS.
	migration down <date>
		downgrade database to specified date. Date must be in format YYYYMMDDHHMMSS.
	migration reset
		execute down of all migrations. If param --force is used, this will drop all tables in database that exist.
	migration rebuild
		execute a reset an up actions.", true));
	}

}

?>