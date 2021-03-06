<?php

$name = 'bind';
$app_id = $app['app_id'];
$unit_text = 'accepted/sec';
$colours = 'psychedelic';
$dostack = 0;
$printtotal = 0;
$addarea = 1;
$transparency = 15;

$rrd_filename = rrd_name($device['hostname'], ['app', 'bind', $app['app_id'], 'sockets']);

$rrd_list = [];
if (rrdtool_check_rrd_exists($rrd_filename)) {
    $rrd_list[] = [
        'filename' => $rrd_filename,
        'descr'    => 'TCP/IPv4',
        'ds'       => 'ti4ca',
    ];
    $rrd_list[] = [
        'filename' => $rrd_filename,
        'descr'    => 'TCP/IPv6',
        'ds'       => 'ti6ca',
    ];
} else {
    d_echo('RRD "' . $rrd_filename . '" not found');
}

require 'includes/html/graphs/generic_multi_line.inc.php';
