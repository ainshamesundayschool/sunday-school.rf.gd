<?php
$query = $_GET;
$query['view'] = 'fast_scan';
$queryString = http_build_query($query);
header("Location: ../index.html?" . $queryString);
exit;
?>
