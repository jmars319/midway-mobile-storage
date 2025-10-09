<?php
// scripts/make_minimal_pdf.php
// Usage: php make_minimal_pdf.php /path/to/out.pdf "Text to render"

$out = $argv[1] ?? null;
$text = $argv[2] ?? 'Placeholder';
if (!$out) {
    fwrite(STDERR, "Usage: php make_minimal_pdf.php /path/to/out.pdf 'Text'\n");
    exit(2);
}

// Build PDF objects and compute xref offsets.
$parts = [];
$header = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$outStr = $header;

// Object helper
$addObj = function($num, $body) use (&$outStr, &$parts) {
    $parts[$num] = "{$num} 0 obj\n{$body}\nendobj\n";
};

$streamContent = "BT /F1 12 Tf 20 700 Td ({$text}) Tj ET\n";
$streamLen = strlen($streamContent);

$addObj(1, "<< /Type /Catalog /Pages 2 0 R >>");
$addObj(2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
$addObj(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>");
$addObj(4, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
$addObj(5, "<< /Length {$streamLen} >>\nstream\n{$streamContent}endstream");

// concatenate parts and record offsets
$offsets = [];
foreach ($parts as $num => $txt) {
    $offsets[$num] = strlen($outStr);
    $outStr .= $txt;
}

$xrefStart = strlen($outStr);
$outStr .= "xref\n0 " . (count($parts) + 1) . "\n";
$outStr .= sprintf("%010d %05d f \n", 0, 65535);
for ($i=1;$i<=count($parts);$i++) {
    $outStr .= sprintf("%010d %05d n \n", $offsets[$i], 0);
}

$outStr .= "trailer\n<< /Size " . (count($parts) + 1) . " /Root 1 0 R>>\nstartxref\n{$xrefStart}\n%%EOF\n";

if (@file_put_contents($out, $outStr) === false) {
    fwrite(STDERR, "Failed to write {$out}\n");
    exit(1);
}
fwrite(STDOUT, "Wrote minimal PDF to {$out} (" . strlen($outStr) . " bytes)\n");
exit(0);
