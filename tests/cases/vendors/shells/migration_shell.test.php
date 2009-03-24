<?php
App::import('Core', array('Shell', 'Folder'));

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}
Mock::generatePartial(
    'ShellDispatcher', 'TestShellMockShellDispatcher',
    array('getInput', 'stdout', 'stderr', '_stop', '_initEnvironment')
);

preg_match("/[\/\\\]plugins[\/\\\]([^\/]+)/", __FILE__, $match);
require_once (APP.'plugins'.DS.$match[1].DS.'vendors'.DS.'shells'.DS.'migration.php');

class TestMigrationShell extends MigrationShell {
    
}
class MigrationShellTestCase extends CakeTestCase {
    var $Shell;
    var $plugin;
    function setUp(){
        $this->Dispatcher =& new TestShellMockShellDispatcher();
        $this->Shell =& new MigrationShell($this->Dispatcher);
    }
    function testStartUp(){
        $this->Shell->params = array (
            'working'=> '',
            'app'=> ''
        );
        $this->Shell->startup();
        
    }
}
?>