<?php

declare(strict_types=1);

require './vendor/autoload.php';

// $input = 'sum = lambda(a, b) {
//     a + b;
//   };
//   print(sum(1, 2));';

$input = file_get_contents('test.hcl');

$s = new HclAstInputStream($input);
$t = new HclAstInputTokenizer($s);
$p = new HclAstInputParser($t);
print_r($p->read());

// while ($r = $t->next()) {
//   print_r($r);
// }
