<?php
/**
 * AppMigration that migrations extends
 *
 * @link          http://github.com/jrbasso/migrations
 * @package       migrations
 * @subpackage    migrations.vendors
 * @since         v 0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * AppMigration
 */
class AppMigration extends Migration {
/**
 * List of models to use
 *
 * @var array
 * @access public
 */
	var $uses = array();
/**
 * Stop on error
 *
 * @var boolean
 * @access public
 */
	var $stopOnError = true;
}
?>