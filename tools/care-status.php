<?php
// Local container diagnostic, no credentials or full backup state printed.
$path='/var/eola-care/job.json';
$job=is_file($path)?json_decode(file_get_contents($path),true):[];
echo json_encode(array_intersect_key($job,array_flip(['kind','status','phase','offset','table_index','row_offset','message'])),JSON_PRETTY_PRINT)."\n";
echo 'Files: '.count($job['files']??[])."\n";
