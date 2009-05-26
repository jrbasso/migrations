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
	 * Last installed version
	 */
	var $lastVersion = 0;

	/**
	 * Schema table in database
	 */
	var $_schemaTable = 'schema_migrations';

	/**
	 * Schema structure
	 */
	var $_schemaStructure = array(
		'id' => array(
			'type' => 'integer',
			'null' => false,
			'default' => NULL,
			'key' => 'primary'
		),
		'version' => array(
			'type' => 'integer',
			'limit' => 11,
			'null' => true,
			'default' => NULL
		),
		'classname' => array(
			'type' => 'string',
			'length' => 128,
			'null' => true,
			'default' => NULL
		),
		'created' => array(
			'type' => 'integer',
			'limit' => 11,
			'null' => true,
			'default' => NULL
		)
	);

	/**
	 * Name of plugin directory
	 */
	var $_pluginName = null;

	/**
	 * Startup script
	 */
	function startup() {
		$this->_paramsParsing();
		
		$this->_startDBConfig();
		$this->_readPathInfo();

		if (empty($this->_versions)) {
			$last = __d('migrations', 'Nothing installed.', true);
		} else {
			$last = end($this->_versions);
			$this->lastVersion = $last['SchemaMigration']['version'];
			$last = date(__d('migrations', 'm/d/Y H:i:s', true), $last['SchemaMigration']['version']);
		}
		
		parent::startup();
		
		$this->out(__d('migrations', 'Migrations Shell', true));
		$this->hr();
		$this->out(String::insert(
			__d('migrations', 'Path to migrations classes: :path', true),
			array('path' => $this->path)
		));
		$this->out(String::insert(
			__d('migrations', 'Connection to the database: :connection', true),
			array('connection' => $this->connection)
		));
		$this->out(String::insert(
			__d('migrations', 'Last migration installed: :date', true),
			array('date' => $last)
		));
		$this->hr();
	}

	function _paramsParsing(){
		if (empty($this->params['path'])) {
			$this->path = APP_PATH . 'config' . DS . 'sql' . DS . 'migrations';
		} else {
			$this->path = rtrim($this->params['path'], DS);
		}
		if (!empty($this->params['connection'])) {
			$this->connection = $this->params['connection'];
		}
		if (preg_match("/[\/\\\]plugins[\/\\\]([^\/]+)[\/\\\]vendors[\/\\\]shells[\/\\\]migration\.php$/", $this->Dispatch->shellPath, $matches)) {
			$this->_pluginName = Inflector::camelize($matches[1]);
		}
	}

	/**
	 * Configs of database
	 */
	function _startDBConfig() {
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
		if (!in_array($this->_schemaTable, $sources)) { // Create schemaTable if not exist
			$this->__createTable();
		} else {
			$this->__checkTable(); // If exist, check the structure
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
		if (isset($this->args[0])) {
			$date = $this->args[0];
			if (!ctype_digit($date) || strlen($date) !== 14) {
				$this->err(__d('migrations', 'Date must be in format YYYYMMDDHHMMSS.', true));
				return false;
			}
			$date = $this->_dateToTimestamp($date);
		} else {
			$date = time(); // Now...
		}
		foreach ($this->_filesInfo as $fileInfo) {
			if ($fileInfo['timestamp'] > $this->lastVersion && $fileInfo['timestamp'] <= $date) {
				$this->out(String::insert(__d('migrations', 'Executing file :file...', true), array('file' => basename($fileInfo['file']))));
				if (!$this->_exec('install', $fileInfo['file'], $fileInfo['classname'])) {
					$this->err(String::insert(__d('migrations', 'Can not be execute :class (:date).', true), array('class' => Inflector::camelize($fileInfo['classname']), 'date' => date(__d('migrations', 'm/d/Y H:i:s', true), $fileInfo['timestamp']))));
					return false;
				}
				$this->SchemaMigration->create();
				$this->SchemaMigration->save(array(
					'SchemaMigration' => array(
						'version' => $fileInfo['timestamp'],
						'classname' => $fileInfo['classname'],
						'created' => time()
					)
				));
			}
		}
		return __d('migrations', 'All updated.', true) . "\n";
	}

	/**
	 * Drop or downgrade database
	 */
	function down($all = false) {
		if ($this->lastVersion == 0) {
			return __d('migration', 'No version installed.', true) . "\n";
		}
		if ($all === true) {
			$date = 0; // Minimal date
		} else {
			if (!isset($this->args[0])) {
				$this->err(__d('migrations', 'Date is needed.', true));
				return false;
			}
			$date = $this->args[0];
			if (!ctype_digit($date) || strlen($date) !== 14) {
				$this->err(__d('migrations', 'Date must be in format YYYYMMDDHHMMSS.', true));
				return false;
			}
			$date = $this->_dateToTimestamp($date);
		}
		end($this->_versions); // Reverse execute
		while (true) {
			$cur = current($this->_versions);
			if ($cur['SchemaMigration']['version'] > $date) {
				$this->out(
					String::insert(__d('migrations',
						'Executing down of :migration (:date)...', true),
						array (
						       'migration' => $cur['SchemaMigration']['classname'],
						       'date' => date(__d('migrations', 'm/d/Y H:i:s', true), $cur['SchemaMigration']['version'])
						)
					)
				);
				$file = $this->path . DS . date('YmdHis', $cur['SchemaMigration']['version']) . '_' . Inflector::underscore($cur['SchemaMigration']['classname']) . '.php';
				if (!$this->_exec('uninstall', $file, $cur['SchemaMigration']['classname'])) {
					$this->err(__d('migrations', 'Error in down.', true));
					return false;
				}
				$this->SchemaMigration->del($cur['SchemaMigration']['id']);
				if (prev($this->_versions)) {
					continue;
				}
			}
			break;
		}
		return __d('migrations', 'All down.', true) . "\n";
	}

	/**
	 * Down all migrations
	 */
	function reset() {
		if ($this->down(true)) {
			if (isset($this->params['force'])) {
				App::import('Vendor', $this->_pluginName . '.Migration');
				$migration = new Migration($this->connection, $this);

				$tables = $this->db->listSources();
				if (!empty($tables)) {
					$this->db->begin($migration);
					foreach ($tables as $table) {
						if ($table == $this->_schemaTable) {
							continue;
						}
						if (!$migration->dropTable($table)) {
							$this->db->rollback($migration);
							$this->err(__d('migrations', 'Can not execute drop of all tables.', true));
							return false;
						}
					}
					$this->db->commit($migration);
				}
			}
			return __d('migrations', 'Resetted.', true) . "\n";
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
	 * Create template
	 */
	function create(){
		if (empty($this->args[0]) || !is_string($this->args[0])){
			$this->err(__d('migrations', 'Let me know the name of migration ...', true));
			$this->_stop();
		}
		$templateDir = dirname(__FILE__) . DS . 'templates' . DS;
		if (!empty($this->params['template'])) {
			$this->_template = $this->params['template'];
			if (!file_exists($this->_template)){
				$this->_template = $templatedir . $this->_template;
				if (!file_exists($this->_template)) {
					$this->err(__d('migrations','I did not find that your template ...',true));
					$this->_stop();
				}
			}
		} else {
			$this->_template = $templateDir . 'single_template.php';
		}
		App::import('Core','File');
		$strings = array (
			'niceName' => Inflector::camelize($this->args[0]),
			'date' => date(__d('migrations','m/d/Y H:i:s',true))
		);
		$template = new File($this->_template);
		$filename = $this->path.DS.date('YmdHis').'_'.Inflector::underscore($this->args[0]).'.php';
		$file = new File($filename, true);
		if (!$file->write(String::insert($template->read(), $strings))){
			$this->err(__d('migrations','Oops, did not write the migration!',true));
			$this->_stop();
		}
		$this->out(__d('migrations','Migration created successfully!', true));
		$this->out(__d('migrations','Go and update the method UP and DOWN', true));
		$this->out(String::insert(
			__d('migrations','The file is in: :file', true),
			array('file'=> $filename)
		));
	}

	/**
	 * Read path info
	 */
	function _readPathInfo() {
		App::import('Core', 'Folder');
		$folder = new Folder($this->path);
		if (!$folder) {
			$this->err(__d('migrations', 'Specified path does not exist.', true));
			$this->out(String::insert(
					__d('migrations', 'Creates the following directory: :path',true),
					array('path'=>APP_PATH.'config'.DS.'sql'.DS.'migrations')
				)
			);
			$this->_stop();
		}
		$read = $folder->read();
		
		$filesInfo = array();
		foreach ($read[1] as $id => $file) { // Check only files
			if (!preg_match('/^(\d{14})_(\w+)\.php/', $file, $matches)) {
				continue;
			}
			$filesInfo[] = array (
				'file' => $this->path . DS . $file,
				'timestamp' => $this->_dateToTimestamp($matches[1]),
				'classname' => Inflector::camelize($matches[2])
			);
		}
		$this->_filesInfo = $filesInfo;
		//$this->_filesInfo = Set::sort($filesInfo, '/timestamp', 'asc');
	}

	/**
	 * Check if class and action exists to execute
	 */
	function _exec($action, $filename, $classname) {
		if (!is_readable($filename)) {
			$this->err(String::insert(__d('migrations', 'File ":file" can not be read. Check if exists or have privileges for your user.', true), array('file'=>$filename)));
			return false;
		}
		App::import('Vendor', $this->_pluginName . '.Migration'); // To not need include in migration file
		if (file_exists(APP_PATH . 'app_migration.php')) {
			include APP_PATH . 'app_migration.php';
		} else {
			App::import('Vendor', $this->_pluginName . '.AppMigration');
		}
		include $filename;
		if (!class_exists($classname)) {
			$this->err(String::insert(__d('migrations', 'The class :classname not in file.', true), array('classname'=>$classname)));
			return false;
		}
		$script = new $classname($this);
		if (!is_subclass_of($script, 'Migration')) {
			$this->err(String::insert(__d('migrations', 'Class :classname not extends Migration.', true), array('classname'=>$classname)));
			return false;
		}
		return $script->$action();
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
		$fakeSchema->tables = array($this->_schemaTable => $this->_schemaStructure);
		if (!$this->db->execute($this->db->createSchema($fakeSchema))) {
			$this->err(String::insert(__d('migrations', 'Schema table ":tablename" can not be created.', true), array('tablename'=>$this->_schemaTable)));
			$this->_stop();
		}else {
			$this->out(String::insert(__d('migrations', 'Schema table ":tablename" created.', true), array('tablename'=>$this->_schemaTable)));
		}
	}

	function __checkTable() {
		$describe = $this->db->describe($this->_schemaTable);
		if (array_keys($describe) == array_keys($this->_schemaStructure)) { // Chaves iguais
			$ok = true;
			foreach ($this->_schemaStructure as $key => $structure) {
				if ($structure['type'] != $describe[$key]['type']) {
					$ok = false;
					break;
				}
			}
			if ($ok) {
				return;
			}
		}
		$fakeSchema = new CakeSchema();
		$fakeSchema->tables = array($this->_schemaTable => '');
		$this->db->execute($this->db->dropSchema($fakeSchema, $this->_schemaTable));
		$this->__createTable();
	}

	/**
	 * Help
	 */
	function help() {
		$this->out(__d('migrations', 'Usage: cake migration <command> <arg1> <arg2>...', true));
		$this->hr();
		$this->out(String::insert(__d('migrations',
"Params:
	-connection <config>
		set db config <config>. Uses 'default' if none is specified.
	-path <dir>
		path <dir> to read and write migrations scripts.
		default path: :path", true),
		array ('path' => $this->params['working'] . DS . 'config' . DS . 'sql' . DS . 'migrations')));

		$this->out(__d('migrations', 
"Commands:
	migration help
		shows this help message.
	migrations create <name>
		create a migration script.
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