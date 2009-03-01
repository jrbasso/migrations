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

		if (empty($this->_versions)) {
			$last = __d('migrations', 'Nothing installed.', true);
		} else {
			$last = end($this->_versions);
			$last = date(__d('migrations', 'm/d/Y H:i:s', true), $last['SchemaMigration']['version']);
		}

		parent::startup();
		$this->out(__d('migrations', 'Migrations Shell', true));
		$this->hr();
		$this->out(sprintf(__d('migrations', 'Path to migrations classes: %s', true), $this->path));
		$this->out(sprintf(__d('migrations', 'Connection to the database: %s', true), $this->connection));
		$this->out(sprintf(__d('migrations', 'Last migration installed: %s', true), $last));
		$this->hr();
	}

	/**
	 * Configs of database
	 */
	function _startDBConfig() {
		config('database');

		App::import('Model', array('ConnectionManager', 'Model'));
		$this->db =& ConnectionManager::getDataSource($this->connection);
		if (!is_subclass_of($this->db, 'DboSource')) {
			$this->err(__d('migrations', 'Your datasource is not supported.', true));
			$this->_stop();
		}
		$this->db->cacheSources = false;

		$sources = $this->db->listSources();
		if (!is_array($sources)) { // Database connection error
			$this->_stop();
		}
		if (!in_array($this->_schemaTable, $sources)) { // Create table if not exists
			$this->__createTable();
		}
		$this->SchemaMigration = new Model(array('name' => 'SchemaMigration', 'table' => $this->_schemaTable, 'ds' => $this->connection));
		$this->_versions = $this->SchemaMigration->find('all', array('order' => array('SchemaMigration.version' => 'ASC')));
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
		//$this->_exec('install');
		return false;
	}

	/**
	 * Drop or downgrade database
	 */
	function down() {
		//$this->_exec('uninstall');
		return false;
	}

	/**
	 * Down all migrations
	 */
	function reset() {
		if ($this->down()) {
			if (isset($this->params['force'])) {
				$fakeSchema = new CakeSchema();
				$fakeSchema->tables = array_flip($this->db->listSources());
				if (isset($fakeSchema->tables[$this->_schemaTable])) {
					unset($fakeSchema->tables[$this->_schemaTable]);
				}
				if (!empty($fakeSchema->tables)) {
					$this->db->begin($fakeSchema);
					foreach ($fakeSchema->tables as $table => $id) {
						if (!$this->db->execute($this->db->dropSchema($fakeSchema, $table))) {
							$this->db->rollback($fakeSchema);
							$this->err(__d('migrations', 'Can not execute drop tables.', true));
							return false;
						}
					}
					$this->db->commit($fakeSchema);
				}
			}
			return __d('migrations', 'Resetted.', true);
		}
		return false;
	}

	/**
	 * Down all migrations an Up later
	 */
	function rebuild() {
		return $this->reset() && $this->up();
	}

	/**
	 * Read path info
	 */
	function _readPathInfo() {
		$filesInfo = array();

		App::import('Core', 'Folder');
		$folder = new Folder();
		if (!$folder->cd($this->path)) {
			$this->err(__d('migrations', 'Specified path does not exist.', true));
			$this->_stop();
		}
		$read = $folder->read();
		$info = array();
		foreach ($read[1] as $id => $file) { // Check only files
			if (!preg_match('/^(\d{14})_(\w+)\.php/', $file, $matches)) {
				continue;
			}
			$file = $this->path . DS . $file;
			$timestamp = $this->_dateToTimestamp($matches[1]);
			$classname = Inflector::camelize($matches[2]);
			$filesInfo[] = compact('file', 'timestamp', 'classname');
		}
		$this->_filesInfo = Set::sort($filesInfo, '{n}.timestamp', 'asc');
	}

	/**
	 * Check if class and action exists to execute
	 */
	function _exec($action) {
		// Generate $filename and $classname
		App::import('Vendor', $this->_pluginName . 'Migration'); // To not need include in migration file
		include $filename;
		if (!class_exists($classname)) {
			$this->err(sprintf(__d('migrations', 'The class %s not in file.', true), $classname));
			$this->_stop();
		}
		$script = new $classname($this->connection);
		if (!is_subclass_of($script, 'Migration')) {
			$this->err(sprintf(__d('migrations', 'Class %s not extends Migration.', true), $classname));
			$this->_stop();
		}
		$ok = $script->$action();
		// TODO: Control for revision
	}

	/**
	 * Timestamp of date in YYYYMMDDHHMMSS format
	 */
	function _dateToTimestamp($date) {
		$data = explode("\r\n", chunk_split($date, 2)); // 0.1 = year, 2 = month, 3 = day, 4 = hour, 5 = minute, 6 second
		array_pop($data);
		return mktime($data[4], $data[5], $data[6], $data[2], $data[3], $data[0] . $data[1]);
	}

	/**
	 * Function to create table of SchemaMigrations
	 */
	function __createTable() {
		$fakeSchema = new CakeSchema();
		$fakeSchema->tables = array(
			$this->_schemaTable => array(
				'id' => array(
					'type' => 'integer',
					'null' => false,
					'default' => NULL,
					'key' => 'primary'
				),
				'version' => array(
					'type' => 'integer',
					'null' => true,
					'default' => NULL
				),
				'created' => array(
					'type' => 'integer',
					'null' => true,
					'default' => NULL
				)
			)
		);
		if (!$this->db->execute($this->db->createSchema($fakeSchema))) {
			$this->err(sprintf(__d('migrations', 'Schema table "%s" can not be created.', true), $this->_schemaTable));
			$this->_stop();
		}
	}

	/**
	 * Help
	 */
	function help() {
		$this->out(__d('migrations', 'Usage: cake migration <command> <arg1> <arg2>...', true));
		$this->hr();
		$this->out(sprintf(__d('migrations',
"Params:
	-connection <config>
		set db config <config>. Uses 'default' if none is specified.
	-path <dir>
		path <dir> to read and write migrations scripts.
		default path: %s", true), $this->params['working'] . DS . 'config' . DS . 'sql' . DS . 'migrations'));
		$this->out(__d('migrations', 
"Commands:
	migration help
		shows this help message.
	migration up [date]
		upgrade database to specified date. If the date is not specified, latest is used. Date must be in format YYYYMMDDHHMMSS.
	migration down <date>
		downgrade database to specified date. Date must be in format YYYYMMDDHHMMSS.
	migration reset
		execute down of all migrations. If param -force is used, this will drop all tables in database that exist.
	migration rebuild
		execute a reset an up actions. Param -force can be used.", true));
	}

}

if (!class_exists('CakeSchema')) {
	class CakeSchema {} // Just to use cake functions
}

?>