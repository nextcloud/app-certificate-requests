<?php

$files = glob("*/*.crt");
$ids = [];

foreach($files as $file) {
	$text = shell_exec("openssl x509 -in $file -noout -text | head -n 4 | tail -n 1");
	preg_match('!: (\d+)!', $text, $match);
	$serialNumber = $match[1];
	list($name, ) = explode('/', $file);

	#echo "$name $serialNumber\n";

	if (isset($ids[$serialNumber])) {
		echo "DUPLICATE: $serialNumber {$ids[$serialNumber]} $name\n";
	} else {
		$ids[$serialNumber] = $name;
	}

}