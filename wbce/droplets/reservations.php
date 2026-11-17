<?php
//:Occupancy calendar of the module Reservations. Inside an object description (news_img post or article) it shows the calendar of that object.
//:[[reservations]] in a news_img post or article linked to an object; [[reservations?resource=ID]] for one object (month calendar); [[reservations?section=ID]] for a whole reservations section as configured there.
defined('WB_PATH') or die('This file cannot be accessed directly!');
if (!is_file(WB_PATH . '/modules/reservations/bootstrap.php')) {
    return '';
}
require_once WB_PATH . '/modules/reservations/bootstrap.php';
return ReservationsWbce::droplet(isset($section) ? (int) $section : null, isset($resource) ? (int) $resource : null);
