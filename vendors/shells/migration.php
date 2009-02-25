<?php

class MigrationShell extends Shell {

	function startup() {
		parent::startup();
		$this->out('Migration Shell');
		$this->hr();
	}

	function main() {
		$this->help();
	}

	function install() {}

	function uninstall() {}

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