<?php

declare(strict_types=1);

require './vendor/autoload.php';

// $input = 'sum = lambda(a, b) {
//     a + b;
//   };
//   print(sum(1, 2));';

// $s = new HclAstInputStream('fsa${function()}cfd');
$s = new HclAstInputStream('ffs');
$t = new HclAstInputTokenizerString($s);
$p = new HclAstInputParserString($t);
print_r($p->parse());
// die;

// while ($r = $t->next()) {
//     print_r($r);
// }
// die;

$input = file_get_contents('test.hcl');
$s = new HclAstInputStream($input);
$t = new HclAstInputTokenizerFile($s);
$p = new HclAstInputParserFile($t);
print_r($p->parse());

// while ($r = $t->next()) {
//   print_r($r);
// }
