<?php
// Nachbau der benoetigten Grocy-API und von Open Food Facts fuer die Tests.
// Start: STATE_DIR=<Ordner> php -S 127.0.0.1:9385 test/mock/grocy.php
// Zustand: <STATE_DIR>/state.json, Anfragen: <STATE_DIR>/requests.log
$dir      = getenv("STATE_DIR") ?: sys_get_temp_dir();
$fixtures = __DIR__ . "/../fixtures";
$fh = fopen("$dir/state.lock", "c"); flock($fh, LOCK_EX);
$state = file_exists("$dir/state.json") ? json_decode(file_get_contents("$dir/state.json"), true) : null;
$now = date("Y-m-d H:i:s");
if ($state === null) {
    $state = array(
        "locations" => array(array("id" => 1, "name" => "Vorratsschrank"), array("id" => 2, "name" => "Kühlschrank"), array("id" => 3, "name" => "Gefrierschrank")),
        "quantity_units" => array(array("id" => 3, "name" => "Packung", "name_plural" => "Packungen"),
            array("id" => 4, "name" => "Stück", "name_plural" => "Stück"), array("id" => 5, "name" => "g", "name_plural" => "g")),
        "quantity_unit_conversions" => array(),
        "product_groups" => array(),
        "products" => array(array("id" => 1, "name" => "Milch", "location_id" => 1, "qu_id_stock" => 3, "default_best_before_days" => 7,
            "enable_tare_weight_handling" => 0, "tare_weight" => 0, "row_created_timestamp" => $now, "picture_file_name" => null),
            // Name mit HTML wie aus manchen Datenquellen
            array("id" => 9, "name" => "<b>Bio</b>-Butter &lt;i&gt;mild&lt;/i&gt;", "location_id" => 2, "qu_id_stock" => 3, "default_best_before_days" => 14,
            "enable_tare_weight_handling" => 0, "tare_weight" => 0, "row_created_timestamp" => $now, "picture_file_name" => null),
            // Bereinigt hiesse es wie ein vorhandenes Produkt ("Milch") und bleibt deshalb unveraendert
            array("id" => 10, "name" => "<b>Milch</b>", "location_id" => 1, "qu_id_stock" => 3, "default_best_before_days" => 7,
            "enable_tare_weight_handling" => 0, "tare_weight" => 0, "row_created_timestamp" => $now, "picture_file_name" => null)),
        "product_barcodes" => array(array("id" => 1, "product_id" => 1, "barcode" => "4000000000001", "amount" => null),
            array("id" => 2, "product_id" => 9, "barcode" => "4000000000009", "amount" => null)),
        "stock" => array(), "files" => array(), "nextId" => 2, "nextStock" => 1,
        "settings" => array("product_presets_location_id" => "-1", "product_presets_qu_id" => "-1", "product_presets_default_due_days" => "0"),
    );
}
$method = $_SERVER["REQUEST_METHOD"];
$path   = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$body   = file_get_contents("php://input");
file_put_contents("$dir/requests.log", "$method {$_SERVER['REQUEST_URI']} " . (strlen($body) > 600 ? "[" . strlen($body) . " bytes]" : $body) . "\n", FILE_APPEND);
function out($code, $data, $raw = false) { global $state, $dir, $fh; file_put_contents("$dir/state.json", json_encode($state)); flock($fh, LOCK_UN);
    http_response_code($code); if ($raw) { echo $data; exit; } header("Content-Type: application/json"); if ($data !== null) echo json_encode($data); exit; }
function &product($id) { global $state; foreach ($state["products"] as $i => $p) if ($p["id"] == $id) return $state["products"][$i]; $n = null; return $n; }

if (preg_match('#^/off/api/v2/product/(\d+)$#', $path, $m)) {
    if (file_exists("$dir/delay_{$m[1]}")) usleep(2500000);
    $f = "$fixtures/off_{$m[1]}.json";
    if (!file_exists($f)) out(404, array("status" => 0));
    out(200, file_get_contents($f), true);
}
if ($path === "/off/img.jpg") { header("Content-Type: image/jpeg"); out(200, file_get_contents("$fixtures/img.jpg"), true); }
if (strpos($path, "/grocy/api/") !== 0) out(404, array());
if (($_SERVER["HTTP_GROCY_API_KEY"] ?? "") !== "testkey") out(401, array("error_message" => "unauthorized"));
$p = substr($path, strlen("/grocy/api/"));

if ($p === "system/info") out(200, array("grocy_version" => array("Version" => "4.7.1")));
if ($p === "user/settings") out(200, $state["settings"]);
if ($method === "GET" && preg_match('#^objects/(\w+)$#', $p, $m)) out(200, $state[$m[1]] ?? array());
if ($method === "GET" && preg_match('#^objects/products/(\d+)$#', $p, $m)) { $pr = product($m[1]); $pr ? out(200, $pr) : out(404, array("error_message" => "nf")); }
if ($method === "POST" && $p === "objects/products") {
    $d = json_decode($body, true);
    $d += array("enable_tare_weight_handling" => 0, "tare_weight" => 0, "default_best_before_days" => 0, "row_created_timestamp" => $now, "picture_file_name" => null);
    $d["id"] = $state["nextId"]++; $state["products"][] = $d; out(200, array("created_object_id" => $d["id"]));
}
if ($method === "PUT" && preg_match('#^objects/products/(\d+)$#', $p, $m)) {
    $pr = &product($m[1]); $d = json_decode($body, true);
    // Wie Grocy: Mengeneinheit nach dem ersten Einkauf nur mit Umrechnung, Bestand wird umgerechnet
    if (isset($d["qu_id_stock"]) && $d["qu_id_stock"] != ($pr["qu_id_stock"] ?? null)) {
        $factor = null;
        foreach ($state["quantity_unit_conversions"] as $c)
            if ($c["from_qu_id"] == ($pr["qu_id_stock"] ?? 0) && $c["to_qu_id"] == $d["qu_id_stock"] && (empty($c["product_id"]) || $c["product_id"] == $pr["id"])) $factor = $c["factor"];
        $hasStock = count(array_filter($state["stock"], function ($s) use ($pr) { return $s["product_id"] == $pr["id"]; })) > 0;
        if ($hasStock && $factor === null) out(400, array("error_message" => "qu_id_stock can only be changed when a corresponding QU conversion (old QU => new QU) exists when the product was once added to stock"));
        foreach ($state["stock"] as $i => $s) if ($s["product_id"] == $pr["id"]) $state["stock"][$i]["amount"] = $s["amount"] * ($factor ?? 1);
    }
    $pr = array_merge($pr, $d); out(204, null);
}
if ($method === "POST" && $p === "objects/quantity_unit_conversions") { $d = json_decode($body, true); $d["id"] = count($state["quantity_unit_conversions"]) + 1; $state["quantity_unit_conversions"][] = $d; out(200, array("created_object_id" => $d["id"])); }
if ($method === "POST" && $p === "objects/product_barcodes") { $d = json_decode($body, true); $d["id"] = count($state["product_barcodes"]) + 1; $d += array("amount" => null); $state["product_barcodes"][] = $d; out(200, array("created_object_id" => $d["id"])); }
if ($method === "PUT" && preg_match('#^files/productpictures/(.+)$#', $p, $m)) { $state["files"][base64_decode($m[1])] = base64_encode($body); out(204, null); }
if ($method === "GET" && preg_match('#^files/productpictures/(.+)$#', $p, $m)) { $f = $state["files"][base64_decode($m[1])] ?? null; $f ? out(200, base64_decode($f), true) : out(404, array()); }
if ($method === "GET" && preg_match('#^stock/products/(\d+)$#', $p, $m)) {
    $pr = product($m[1]); if (!$pr) out(400, array("error_message" => "Product does not exist"));
    $amount = 0; foreach ($state["stock"] as $s) if ($s["product_id"] == $pr["id"]) $amount += $s["amount"];
    out(200, array("product" => $pr, "quantity_unit_stock" => array("name" => "Packung"), "product_barcodes" => array(), "stock_amount" => $amount));
}
if ($method === "POST" && preg_match('#^stock/products/(\d+)/add$#', $p, $m)) {
    $d = json_decode($body, true); $pr = product($m[1]);
    $row = array("id" => $state["nextStock"]++, "stock_id" => uniqid(), "product_id" => (int)$m[1], "amount" => $d["amount"],
        "best_before_date" => $d["best_before_date"] ?? "2999-12-31", "purchased_date" => date("Y-m-d"), "location_id" => $d["location_id"] ?? $pr["location_id"],
        "shopping_location_id" => null, "price" => $d["price"] ?? null, "open" => 0, "note" => null);
    $state["stock"][] = $row;
    out(200, array(array("id" => 100 + $row["id"], "product_id" => $row["product_id"], "amount" => $row["amount"], "best_before_date" => $row["best_before_date"],
        "stock_id" => $row["stock_id"], "transaction_type" => "purchase", "location_id" => $row["location_id"], "transaction_id" => uniqid())));
}
if ($method === "GET" && preg_match('#^stock/products/(\d+)/entries$#', $p, $m)) out(200, array_values(array_filter($state["stock"], function ($s) use ($m) { return $s["product_id"] == $m[1]; })));
if ($method === "PUT" && preg_match('#^stock/entry/(\d+)$#', $p, $m)) {
    $d = json_decode($body, true);
    foreach (array("amount", "open", "purchased_date") as $req) if (!array_key_exists($req, $d)) out(400, array("error_message" => "missing $req"));
    foreach ($state["stock"] as $i => $s) if ($s["id"] == $m[1]) { $state["stock"][$i] = array_merge($s, $d); out(200, array()); }
    out(400, array("error_message" => "Stock does not exist"));
}
if ($method === "POST" && $p === "stock/shoppinglist/remove-product") out(204, null);
out(400, array("error_message" => "Nachbau: unbekannter Aufruf $method $p"));
