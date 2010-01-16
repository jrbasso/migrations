<?php
/**
 * Tests of Migration Shell
 *
 * @link          http://github.com/jrbasso/migrations
 * @package       migrations
 * @subpackage    migrations.tests.cases.vendors.shells
 * @since         v 0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

// Importing classes
App::import('Core', array('Shell', 'Folder'));

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' . DS . 'cake.php';
	ob_end_clean();
}

Mock::generatePartial(
	'ShellDispatcher', 'TestShellMockShellDispatcher',
	array('getInput', 'stdout', 'stderr', '_stop', '_initEnvironment')
);

preg_match("/[\/\\\]plugins[\/\\\]([^\/]+)/", __FILE__, $match);
require_once (APP . 'plugins' . DS . $match[1] . DS . 'vendors' . DS . 'shells' . DS . 'migration.php');

class TestMigrationShell extends MigrationShell {
}

class MigrationShellTestCase extends CakeTestCase {
/**
 * Migration Shell
 *
 * @var object
 * @access public
 */
	var $Shell;

/**
 * setUp function
 *
 * @return void
 * @access public
 */
	function setUp() {
		$this->Dispatcher =& new TestShellMockShellDispatcher();
		$this->Shell =& new MigrationShell($this->Dispatcher);
	}

/**
 * testStartUp function
 *
 * @return void
 * @access public
 */
	function testStartUp(){
		$this->Shell->params = array (
			'working' => '',
			'app' => ''
		);
		$this->Shell->startup();
	}
}
?>