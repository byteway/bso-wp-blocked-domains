<?php
// Simple PO -> MO converter.
if ($argc < 3) {
    echo "Usage: php po2mo.php input.po output.mo\n";
    exit(1);
}
$in = $argv[1];
$out = $argv[2];
if (!file_exists($in)) { echo "Input file missing\n"; exit(2); }
$po = file_get_contents($in);
$entries = array();
$msgid = null; $msgstr = null; $in_msgid = false; $in_msgstr = false;
$lines = preg_split('/\R/', $po);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line,'#') === 0) continue;
    if (strpos($line,'msgid') === 0) {
        $in_msgid = true; $in_msgstr = false; $msgid = trim(substr($line,5));
        $msgid = trim($msgid);
        $msgid = trim($msgid, '"');
        if ($msgid === '') $msgid = '';
        continue;
    }
    if (strpos($line,'msgstr') === 0) {
        $in_msgstr = true; $in_msgid = false; $msgstr = trim(substr($line,6));
        $msgstr = trim($msgstr);
        $msgstr = trim($msgstr, '"');
        if ($msgstr === null) $msgstr = '';
        // finish pair
        if ($msgid !== null) {
            $entries[$msgid] = $msgstr;
        }
        $msgid = null; $msgstr = null;
        continue;
    }
    // continued string lines like "..."
    if (preg_match('/^"(.*)"$/', $line, $m)) {
        if ($in_msgid && $msgid !== null) {
            $msgid .= $m[1];
        } elseif ($in_msgstr && $msgstr !== null) {
            $msgstr .= $m[1];
        }
    }
}
// Build mo binary
$offsets = array();
$ids = array_keys($entries);
sort($ids);
$translations = array();
foreach ($ids as $id) {
    $translations[$id] = $entries[$id];
}
$originals = array(); $translated = array();
foreach ($translations as $o => $t) {
    $originals[] = $o;
    $translated[] = $t;
}
$originals_blob = '';
$translated_blob = '';
$orig_table = array();
$trans_table = array();
$offset = 28 + (count($originals) * 8) + (count($translated) * 8);
foreach ($originals as $o) {
    $len = strlen($o);
    $orig_table[] = array($len, $offset);
    $originals_blob .= $o . "\0";
    $offset += $len + 1;
}
foreach ($translated as $t) {
    $len = strlen($t);
    $trans_table[] = array($len, $offset);
    $translated_blob .= $t . "\0";
    $offset += $len + 1;
}
$magic = 0x950412de;
$rev = 0;
$nb = count($originals);
$header = pack('I4', $magic, $rev, $nb, 28);
$orig_index_offset = 28;
$trans_index_offset = 28 + ($nb * 8);
$header .= '';
$orig_index = '';
$trans_index = '';
foreach ($orig_table as $i) {
    $orig_index .= pack('I2', $i[0], $i[1]);
}
foreach ($trans_table as $i) {
    $trans_index .= pack('I2', $i[0], $i[1]);
}
$data = $header . $orig_index . $trans_index . $originals_blob . $translated_blob;
if (file_put_contents($out, $data) === false) {
    echo "Failed writing mo\n"; exit(3);
}
echo "Wrote $out\n";
?>