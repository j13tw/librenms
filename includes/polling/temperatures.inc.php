<?php

$query = "SELECT * FROM sensors WHERE sensor_class='temperature' AND device_id = '" . $device['device_id'] . "' AND poller_type='snmp'";
$temp_data = mysql_query($query);
while ($sensor = mysql_fetch_assoc($temp_data))
{
  echo("Checking temp " . $sensor['sensor_descr'] . "... ");

  for ($i = 0;$i < 5;$i++) # Try 5 times to get a valid temp reading
  {
    if ($debug) echo("Attempt $i ");
    $temp = snmp_get($device, $sensor['sensor_oid'], "-OUqnv", "SNMPv2-MIB");
    $temp = trim(str_replace("\"", "", $temp));

    if ($temp != 9999) break; # TME sometimes sends 999.9 when it is right in the middle of an update;
    sleep(1); # Give the TME some time to reset
  }

  if ($sensor['sensor_divisor'])    { $temp = $temp / $sensor['sensor_divisor']; }
  if ($sensor['sensor_multiplier']) { $temp = $temp * $sensor['sensor_multiplier']; }

  $old_rrd_file  = $config['rrd_dir'] . "/" . $device['hostname'] . "/" . safename("temperature-" . $sensor['sensor_descr'] . ".rrd");
  $rrd_file = $config['rrd_dir'] . "/" . $device['hostname'] . "/temperature-" . safename($sensor['sensor_type']."-".$sensor['sensor_index']) . ".rrd";

  if (is_file($old_rrd_file)) { rename($old_rrd_file, $rrd_file); }

  if (!is_file($rrd_file))
  {
    rrdtool_create($rrd_file,"--step 300 \
     DS:sensor:GAUGE:600:-273:1000 \
     RRA:AVERAGE:0.5:1:600 \
     RRA:AVERAGE:0.5:6:700 \
     RRA:AVERAGE:0.5:24:775 \
     RRA:AVERAGE:0.5:288:797 \
     RRA:MAX:0.5:1:600 \
     RRA:MAX:0.5:6:700 \
     RRA:MAX:0.5:24:775 \
     RRA:MAX:0.5:288:797\
     RRA:MIN:0.5:1:600 \
     RRA:MIN:0.5:6:700 \
     RRA:MIN:0.5:24:775 \
     RRA:MIN:0.5:288:797");
  }

  echo($temp . "C\n");

  rrdtool_update($rrd_file,"N:$temp");

  if ($sensor['sensor_limit_low'] != "" && $sensor['sensor_current'] < $sensor['sensor_limit'] && $temp >= $sensor['sensor_limit'])
  {
    $msg  = "Temp Alarm: " . $device['hostname'] . " " . $sensor['sensor_descr'] . " is " . $temp . " (Limit " . $sensor['sensor_limit'];
    $msg .= ") at " . date($config['timestamp_format']);
    notify($device, "Temp Alarm: " . $device['hostname'] . " " . $sensor['sensor_descr'], $msg);
    echo("Alerting for " . $device['hostname'] . " " . $sensor['sensor_descr'] . "\n");
    log_event('Temperature ' . $sensor['sensor_descr'] . " over threshold: " . $temp . " " . html_entity_decode('&deg;') . "C (>= " . $sensor['sensor_limit'] . " " . html_entity_decode('&deg;') . 'C)', $device, 'temperature', $sensor['sensor_id']);
  }

  mysql_query("UPDATE sensors SET sensor_current = '$temp' WHERE sensor_class='temperature' AND sensor_id = '" . $sensor['sensor_id'] . "'");
}

?>