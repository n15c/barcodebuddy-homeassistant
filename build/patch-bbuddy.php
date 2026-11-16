<?php
// Wird beim Bauen des Images ausgefuehrt: haengt die Home-Assistant-Erweiterung fuer die
// automatische Produktanlage in BarcodeBuddy ein. Bricht den Build ab, wenn sich der
// BarcodeBuddy-Code geaendert hat und die Stelle nicht mehr gefunden wird.

$file      = "/app/bbuddy/incl/processing.inc.php";
$signature = 'function processUnknownBarcode(string $barcode, bool $websocketEnabled, LockGenerator &$fileLock, ?string $bestBeforeInDays, ?string $price): string {';
$hook      = <<<'PHP'

    // Home-Assistant-Add-on: unbekannte Produkte aus Open Food Facts in Grocy anlegen
    require_once __DIR__ . "/haAutoCreate.inc.php";
    $haResult = HaAutoCreate::tryCreate($barcode, $websocketEnabled, $fileLock, $bestBeforeInDays, $price);
    if ($haResult !== null)
        return $haResult;
PHP;

$code = file_get_contents($file);
if ($code === false || substr_count($code, $signature) !== 1) {
    fwrite(STDERR, "processUnknownBarcode() nicht gefunden - BarcodeBuddy-Version passt nicht zum Patch\n");
    exit(1);
}
if (strpos($code, "HaAutoCreate::tryCreate") === false) {
    $code = str_replace($signature, $signature . $hook, $code);
    if (file_put_contents($file, $code) === false) {
        fwrite(STDERR, "Konnte $file nicht schreiben\n");
        exit(1);
    }
}
echo "BarcodeBuddy: Hook fuer automatische Produktanlage eingefuegt\n";
