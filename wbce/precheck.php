<?php
/**
 * Reservations – precheck.php
 * Requirements checked by the WBCE installer before the module is installed.
 * 1.7.0: the PDO-based database class (also SQLite) and Lang; intl formats dates.
 * @module reservations  @version 0.6.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

$PRECHECK = [
    'WBCE_VERSION'   => ['VERSION' => '1.7.0', 'OPERATOR' => '>='],
    'PHP_VERSION'    => ['VERSION' => '8.2', 'OPERATOR' => '>='],
    'PHP_EXTENSIONS' => ['intl'],
];
