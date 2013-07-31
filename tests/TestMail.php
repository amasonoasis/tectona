<?php
// work with composer by default
$include = realpath(__DIR__.'/../vendor/autoload.php');
include_once $include;

use Tectona\Mailer;
error_reporting( E_ALL|E_STRICT );
var_dump($argv);

$file   = realpath('../docs/example.xml');
$xsl    = realpath('../docs/example.xsl');

$mailer = new Mailer($xsl);
$mailer->setSourceFile($source);
// echo $mailer->dom->saveXML();
$subs = array(
    'Message' => 'Foo foo bar lorem ipsum',
    'Name'    => 'John Doe',
);

$mailer->appendArrayToSubs($subs);

$mailer->merge();
#echo $mailer->dom->saveXML();
echo "========== TEXT ===== \n";
echo $mailer->body['text'];
echo "\n ========== END TEXT ==========\n";
echo "========== HTML ===== \n";
echo $mailer->body['html'];
echo "\n ========== END HTML ==========\n";
/*
$subsXML = '<subs>
<title>Mrs</title>
<firstName>Foo</firstName>
<lastName>Bar</lastName>
<friend>Bob T. Builder</friend>
<link>http://www.flah.com</link>
</subs>';
$x = $mailer->appendXMLToSubs( $subsXML );*/


