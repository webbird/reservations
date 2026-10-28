<?php
/**
 * Reservations – uninstall.php
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

Reservations\Core\Storage\Schema::uninstall(ReservationsWbce::db());
