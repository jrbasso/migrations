<?php

class MigrationShell extends Shell {

	/*
	 * Path to installers files
	 */
	var $path = null;

	/*
	 * Name of database
	 */
	var $connection = 'default';

	/*
	 * Number of revision to install/uninstall. -1 if latest
	 */
	var $revision = -1;

	/*
	 * Startup script
	 */
	function startup() {
		if (empty($this->params['path'])) {
			$this->path = $this->params['working'] . DS . 'installers';
		} else {
			$this->path = $this->params['path'];
		}

		if (!empty($this->params['connection'])) {
			$this->connection = $this->params['connection'];
		}

		if (!empty($this->params['revision'])) {
			$this->revision = $this->params['revision'];
		}

		parent::startup();
		$this->out('Migration Shell');
		$this->hr();
		$this->out('Path to migrations scripts: ' . $this->path);
		$this->out('Connection to the database: ' . $this->connection);
		$this->out('Revision: ' . ($this->revision === -1 ? 'Latest' : $this->revision));
		$this->hr();
	}

	/*
	 * Main
	 */
	function main() {
		$this->help();
	}

	/*
	 * Install or update database
	 */
	function install() {
		$this->_exec('install');
	}

	/*
	 * Drop or downgrade database
	 */
	function uninstall() {
		$this->_exec('uninstall');
	}

	/*
	 * Check if class and action exists to execute
	 */
	function _exec($action) {
		extract($this->_selectFile()); // Generate $filename and $classname
		App::import('Vendor', 'Migrations.Migration'); // To not need include in installer file
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
		$ok = $script->$action($this->revision, $this->connection);
		// TODO: Control for revision
	}

	/*
	 * Select a file from installer dir
	 */
	function _selectFile() {
		App::import('Core', 'Folder');
		$folder = new Folder();
		if (!$folder->cd($this->path)) {
			$this->err('Specified path not exist.');
			$this->_stop();
		}
		$read = $folder->read();
		$i = 1;
		$readFiles = array();
		foreach ($read[1] as $id => $file) { // Remove files that not is a installer or remove the extension
			if (strlen($file) < 15 || substr($file, -14) !== '_installer.php') {
				continue;
			}
			$readFiles[$i++] = substr($file, 0, -14);
		}
		unset($read);
		if (empty($readFiles)) {
			$this->err('Specified path not have installer scripts.');
			$this->_stop();
		}

		$readSize = count($readFiles);
		do {
			$this->out("\n\nPlease, select a file:");
			foreach ($readFiles as $id => $file) {
				$this->out('[' . $id . '] ' . Inflector::humanize($file));
			}
			$this->out("[0] Exit. Nothing is changed.\n");
			$selected = $this->in('Chose: ');
		} while ($selected < 0 || $selected > $readSize);

		if ($selected == 0) { // Exit option
			$this->out('Good bye.');
			$this->_stop();
		}

		// Return a full path for file and classname
		return array(
			'filename' => $this->path . DS . $readFiles[$selected] . '_installer.php',
			'classname' => Inflector::camelize($readFiles[$selected]) . 'Installer'
		);
	}

	/*
	 * Help
	 */
	function help() {
		$this->out("Usage: cake migration <command> <arg1> <arg2>...");
		$this->hr();
		$this->out('Params:');
		$this->out("\n\t-connection <config>\n\t\tset db config <config>. Uses 'default' if none is specified");
		$this->out("\n\t-path <dir>\n\t\tpath <dir> to read and write installer scripts.\n\t\tdefault path: " . $this->params['app'] .  DS . 'installers');
		$this->out("\n\t-revision <number>\n\t\trevision used do install/uninstall.");
		$this->out('Commands:');
		$this->out("\n\tmigration help\n\t\tshows this help message.");
		$this->out("\n\tmigration install\n\t\tupgrade database to specified revision. Uses latest if none is specified.");
		$this->out("\n\tmigration uninstall\n\t\tdowngrade database to specified revision. Uses latest if none is specified.");
		$this->out("");
		$this->_stop();
	}

}

?>